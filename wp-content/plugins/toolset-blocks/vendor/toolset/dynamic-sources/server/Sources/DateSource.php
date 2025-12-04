<?php

namespace Toolset\DynamicSources\Sources;

use Toolset\DynamicSources\DynamicSources;

/**
 * Abstract for all Date* sources, simply to DRY the common code for them all.
 *
 * @package Toolset\DynamicSources\Sources
 */
abstract class DateSource extends AbstractSource {
    /**
     * Gets the Source group.
     *
     * @return string
     */
    public function get_group() {
        return DynamicSources::POST_GROUP;
    }

    /**
     * Gets the Source categories, i.e. the type of content this Source can offer.
     *
     * @return array
     */
    public function get_categories() {
        return array( DynamicSources::TEXT_CATEGORY );
    }

    /**
     * @param string $date
     * @param string $format
     *
     * @return string
     */
    protected function date_format( $date, $format ) {
        // Use WordPress date_i18n for proper localization support
        if ( function_exists( 'date_i18n' ) ) {
            $timestamp = strtotime( $date );
            if ( $timestamp === false ) {
                return (string) date_format( date_create( $date ), $format );
            }

            // Try to use date_i18n for localization
            $formatted = date_i18n( $format, $timestamp );

            // If date_i18n returns null or empty fallback to the original method
            if ( $formatted === null || $formatted === '' || $formatted === false ) {
                return (string) date_format( date_create( $date ), $format );
            }

            return $formatted;
        } else {
            // Fallback to original method
            return (string) date_format( date_create( $date ), $format );
        }
    }

    /**
     * @param array|null $attributes
     * @param string $date
     *
     * @return string
     */
    protected function maybe_formatted( $attributes, $date ) {
        if (
            $attributes
            && array_key_exists( 'format', $attributes )
        ) {
            return $this->date_format( $date, $attributes['format'] );
        } else {
            return $date;
        }
    }
}