(function(blocks, element, components, blockEditor, i18n) {
    const { registerBlockType } = blocks;
    const { createElement: el } = element;
    const { TextControl, PanelBody, ToggleControl, SelectControl } = components;
    const { InspectorControls } = blockEditor;
    const { __ } = i18n;

    registerBlockType('maloney-listings/available-units', {
        title: __('Current Rental Availability', 'maloney-listings'),
        icon: 'list-view',
        category: 'widgets',
        description: __('Display a table of all available rental units from all properties.', 'maloney-listings'),
        attributes: {
            title: {
                type: 'string',
                default: 'Current Rental Availability',
            },
        },
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { title } = attributes;

            return el('div', { className: 'maloney-available-units-block-editor' },
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Block Settings', 'maloney-listings'), initialOpen: true },
                        el(TextControl, {
                            label: __('Title', 'maloney-listings'),
                            value: title,
                            onChange: function(value) {
                                setAttributes({ title: value });
                            },
                            help: __('The title displayed above the availability table.', 'maloney-listings'),
                        })
                    )
                ),
                el('div', { className: 'maloney-available-units-preview' },
                    el('h3', {}, title || __('Current Rental Availability', 'maloney-listings')),
                    el('p', { style: { color: '#666', fontStyle: 'italic' } },
                        __('This block will display a table of all available rental units from all properties.', 'maloney-listings')
                    )
                )
            );
        },
        save: function() {
            // Server-side rendering
            return null;
        },
    });

    registerBlockType('maloney-listings/listings-view', {
        title: __('Listings View', 'maloney-listings'),
        icon: 'location-alt',
        category: 'widgets',
        description: __('Display the full listings page with map, filters, and search functionality.', 'maloney-listings'),
        attributes: {
            type: {
                type: 'string',
                default: 'units',
            },
        },
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { type } = attributes;

            return el('div', { className: 'maloney-listings-view-block-editor' },
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Block Settings', 'maloney-listings'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Listing Type Filter', 'maloney-listings'),
                            value: type,
                            options: [
                                { label: __('All Listings', 'maloney-listings'), value: 'units' },
                                { label: __('Condominiums Only', 'maloney-listings'), value: 'condo' },
                                { label: __('Rentals Only', 'maloney-listings'), value: 'rental' },
                            ],
                            onChange: function(value) {
                                setAttributes({ type: value });
                            },
                            help: __('Filter listings by type. "All Listings" shows both condos and rentals.', 'maloney-listings'),
                        })
                    )
                ),
                el('div', { className: 'maloney-listings-view-preview', style: { padding: '20px', border: '1px solid #ddd', borderRadius: '4px' } },
                    el('h3', {}, __('Listings View', 'maloney-listings')),
                    el('p', { style: { color: '#666', fontStyle: 'italic' } },
                        __('This block will display the full listings page with:', 'maloney-listings')
                    ),
                    el('ul', { style: { marginLeft: '20px', color: '#666' } },
                        el('li', {}, __('Interactive map with clustering', 'maloney-listings')),
                        el('li', {}, __('Filterable listing cards', 'maloney-listings')),
                        el('li', {}, __('Search functionality', 'maloney-listings')),
                        el('li', {}, __('Pagination', 'maloney-listings'))
                    ),
                    el('p', { style: { marginTop: '10px', color: '#333', fontWeight: 'bold' } },
                        __('Type Filter:', 'maloney-listings') + ' ' + (type === 'units' ? __('All Listings', 'maloney-listings') : type === 'condo' ? __('Condominiums Only', 'maloney-listings') : __('Rentals Only', 'maloney-listings'))
                    )
                )
            );
        },
        save: function() {
            // Server-side rendering
            return null;
        },
    });

    registerBlockType('maloney-listings/search-form', {
        title: __('Listings Search Form', 'maloney-listings'),
        icon: 'search',
        category: 'widgets',
        description: __('A search form with Condo/Rental tabs that redirects to the listings page with type and location pre-selected.', 'maloney-listings'),
            attributes: {
                placeholder: {
                    type: 'string',
                    default: 'Search location or zip code...',
                },
            buttonText: {
                type: 'string',
                default: 'Get started',
            },
            showTabs: {
                type: 'boolean',
                default: true,
            },
        },
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { placeholder, buttonText, showTabs } = attributes;
            const { ToggleControl } = components;

            return el('div', { className: 'maloney-search-form-block-editor' },
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Block Settings', 'maloney-listings'), initialOpen: true },
                        el(ToggleControl, {
                            label: __('Show Condo/Rental Tabs', 'maloney-listings'),
                            checked: showTabs,
                            onChange: function(value) {
                                setAttributes({ showTabs: value });
                            },
                            help: __('Display tabs to select between Condo and Rental listings.', 'maloney-listings'),
                        }),
                        el(TextControl, {
                            label: __('Placeholder Text', 'maloney-listings'),
                            value: placeholder,
                            onChange: function(value) {
                                setAttributes({ placeholder: value });
                            },
                            help: __('The placeholder text shown in the search input field.', 'maloney-listings'),
                        }),
                        el(TextControl, {
                            label: __('Button Text', 'maloney-listings'),
                            value: buttonText,
                            onChange: function(value) {
                                setAttributes({ buttonText: value });
                            },
                            help: __('The text displayed on the search button.', 'maloney-listings'),
                        })
                    )
                ),
                el('div', { className: 'maloney-search-form-preview', style: { padding: '20px', border: '1px solid #ddd', borderRadius: '4px' } },
                    el('div', { style: { marginBottom: '10px', fontWeight: 'bold' } },
                        __('Listings Search Form', 'maloney-listings')
                    ),
                    showTabs && el('div', { style: { display: 'flex', gap: '0', marginBottom: '15px', borderBottom: '2px solid #e0e0e0' } },
                        el('button', {
                            style: { padding: '8px 16px', background: '#f5f5f5', color: '#333', border: 'none', borderBottom: '3px solid transparent', cursor: 'default' },
                            disabled: true
                        }, 'Condo'),
                        el('button', {
                            style: { padding: '8px 16px', background: 'white', color: '#333', border: 'none', borderBottom: '3px solid #0073aa', cursor: 'default', fontWeight: 'bold' },
                            disabled: true
                        }, 'Rental')
                    ),
                    el('div', { style: { display: 'flex', gap: '10px', alignItems: 'center', background: 'white', border: '2px solid #ddd', borderRadius: '8px', padding: '5px' } },
                        el('input', {
                            type: 'text',
                            placeholder: placeholder || 'Search location or zip code...',
                            style: { flex: 1, padding: '8px 12px', border: 'none', borderRadius: '4px', background: 'transparent' },
                            disabled: true
                        }),
                        el('button', {
                            style: { padding: '8px 20px', background: '#dc3232', color: 'white', border: 'none', borderRadius: '6px', cursor: 'default', fontWeight: 'bold' },
                            disabled: true
                        }, buttonText || 'Get started')
                    ),
                    el('p', { style: { marginTop: '10px', color: '#666', fontSize: '12px', fontStyle: 'italic' } },
                        __('Users can select Condo or Rental, then search by city or zip code. The form will redirect to the listings page with the type and location pre-selected.', 'maloney-listings')
                    )
                )
            );
        },
        save: function() {
            // Server-side rendering
            return null;
        },
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.blockEditor || window.wp.editor,
    window.wp.i18n
);

