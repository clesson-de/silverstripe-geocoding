/**
 * Rollup configuration for the Geocoding admin assets.
 *
 * Output filenames are static (no content hashes) because they are
 * referenced by name in PHP via Requirements::css() / Requirements::javascript()
 * and must not change between builds.
 *
 * CSS is compiled separately via the `build:css` npm script using the Sass CLI
 * to guarantee a stable output filename (map-field.css).
 */
export default [
    {
        input: 'client/admin/src/map-utils.js',
        output: {
            file: 'client/admin/dist/map-utils.js',
            format: 'iife',
            // No hashing — filename must be stable so it can be referenced in PHP
        },
    },
    {
        input: 'client/admin/src/google-map.js',
        output: {
            file: 'client/admin/dist/google-map.js',
            format: 'iife',
            // No hashing — filename must be stable so it can be referenced in PHP
        },
    },
    {
        input: 'client/admin/src/osm-map.js',
        output: {
            file: 'client/admin/dist/osm-map.js',
            format: 'iife',
            // No hashing — filename must be stable so it can be referenced in PHP
        },
    },
    {
        input: 'client/admin/src/map-entwine.js',
        output: {
            file: 'client/admin/dist/map-entwine.js',
            format: 'iife',
        },
    },
    {
        input: 'client/admin/src/address-field.js',
        output: {
            file: 'client/admin/dist/address-field.js',
            format: 'iife',
        },
    },
];


