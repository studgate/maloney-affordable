/**
 * This class implements the Update Provider Health widget functionality.
 *
 * The AJAX endpoint should return a JSON object with the following structure:
 *
 * {
 *     success: boolean,
 *     data: {
 *         <provider_name>: {
 *              name: string,
 *              display_name: string,
 *              last_connection_check_timestamp_in_seconds,
 *              next_connection_check_timestamp_in_seconds,
 *              last_connection_check_result: boolean,
*              }
 *     }
 * }
 */
class WpeUpdateProviderHealth {
    /**
     * Type for response from the provider health AJAX endpoint.
     *
     * @typedef {Object} ProviderHealthDataResponse
     *
     * @property {boolean} success
     * @property {ProviderHealthDataList} data
     */

    /**
     * Type for the list of provider health data from the AJAX endpoint.
     *
     * @typedef {Object.<string,ProviderHealthData>} ProviderHealthDataList
     */

    /**
     * Type for the provider health data.
     *
     * This should match the properties of the PHP AbstractUpdateProvider class.
     *
     * @typedef {Object} ProviderHealthData
     *
     * @property {string} name
     * @property {string} display_name
     * @property {number} last_connection_check_timestamp_in_seconds
     * @property {number} next_connection_check_timestamp_in_seconds
     * @property {boolean} last_connection_check_result
     */


    /**
     * Constructor
     */
    constructor() {
        // Nothing to do?
    }

    /**
     * Initialize the provider health widget.
     */
    async init() {
        if (!this.pageHasProviderHealthWidget()) {
            return;
        }

        const data = await this.fetchProviderHealthData();

        if (!data) {
            return;
        }

        this.updateProviderHealthWidget(data);
    }

    /**
     * Fetch the provider health data from the server.
     *
     * @returns {(false|ProviderHealthDataList)}
     */
    async fetchProviderHealthData() {
        // wpe_update_provider_health is defined in the localized script.
        const response = await fetch(wpe_update_provider_health.ajax_url, {
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            method: 'POST',
            body: new URLSearchParams({
                action: 'wpe_update_provider_health_check',
                _ajax_nonce: wpe_update_provider_health.nonce
            })
        });

        if (response.status !== 200) {
            console.error(`Error fetching provider health data. Code: ${response.status}.`);
            this.showProviderHealthApiError('An error occurred when fetching provider health data.');
            this.hideContentOnError();
            return false;
        }

        /**
         * @type {ProviderHealthDataResponse}
         */
        let responseData;

        try {
            responseData = await response.json();
        } catch (e) {
            console.error('Error parsing provider health data JSON');
            this.showProviderHealthApiError('An error occurred when fetching provider health data.');
            return false;
        }

        if (!responseData || !responseData.success) {
            console.error('Error parsing provider health data JSON');
            this.showProviderHealthApiError('An error occurred when fetching provider health data.');
            return false;
        }

        return responseData.data;
    }

    /**
     * Returns the Provider Health widget element.
     *
     * @returns {(HTMLElement|null)}
     */
    getProviderHealthWidget() {
        return document.getElementById('wpe-provider-status-widget');
    }

    /**
     * Returns true if the current page has the Provider Health widget.
     *
     * @returns {boolean}
     */
    pageHasProviderHealthWidget() {
        // The !! does a cast to a boolean
        return !!this.getProviderHealthWidget();
    }

    /**
     * Updates the Provider Health widget with the data provided.
     *
     * @param {ProviderHealthDataList} data
     */
    updateProviderHealthWidget(data) {
        const widget = this.getProviderHealthWidget();

        if (!widget) {
            return;
        }

        const statusContent = document.createElement('ul');

        Object.entries(data).forEach(([name, provider]) => {
            statusContent.appendChild(this.makeStatusElement(provider));
        })

        widget.replaceChildren(statusContent);
    }

    /**
     * Creates an HTML status element for a provider.
     *
     * @param {ProviderHealthData} provider
     */
    makeStatusElement(provider) {
        const statusContainer = document.createElement('li');
        statusContainer.classList.add('wpe-provider-status');
        statusContainer.classList.add(provider.last_connection_check_result ? 'wpe-provider-status--good' : 'wpe-provider-status--bad');

        const statusText = provider.last_connection_check_result ? 'reachable' : 'unreachable';
        // Need to *1000 because JS expects milliseconds.
        const lastConnectTime = new Date(provider.last_connection_check_timestamp_in_seconds * 1000).toISOString()
        const nextConnectTime = new Date(provider.next_connection_check_timestamp_in_seconds * 1000).toISOString();

        statusContainer.innerHTML = `<span>${provider.display_domain} is <strong>${statusText}</strong> from this site.</span>`;
        statusContainer.dataset.lastCheckTime = lastConnectTime;
        statusContainer.dataset.nextCheckTime = nextConnectTime;

        return statusContainer;
    }

    /**
     * Show an error message in the panel.
     *
     * @param {string} error
     */
    showProviderHealthApiError(error) {
        const widget = this.getProviderHealthWidget();

        if (!widget) {
            return;
        }

        const errorElement = document.createElement('div');
        errorElement.classList.add('wpe-provider-status--error');
        errorElement.innerText = error;
        widget.replaceChildren(errorElement);
    }

    hideContentOnError() {
        const widget = this.getProviderHealthWidget();

        if (!widget) {
            return;
        }
        const container = widget.parentElement;

        const elemsToDelete = container.querySelectorAll('[data-remove-on-error="1"]');

        elemsToDelete.forEach((elem) => {
            elem.remove();
        });
    }
}

const wpeUpdateProviderHealth = new WpeUpdateProviderHealth();
wpeUpdateProviderHealth.init();
