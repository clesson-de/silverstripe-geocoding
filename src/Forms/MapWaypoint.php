<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Forms;

/**
 * Represents a waypoint used in a route displayed on a MapField.
 *
 * A waypoint has a position and optional visual properties (icon, info window).
 * Waypoints are purely decorative — they are not saved when the form is submitted.
 *
 * Usage:
 * ```php
 * $waypoint = MapWaypoint::create(50.9333, 6.9500)
 *     ->setTitle('Cologne')
 *     ->setInfoWindow('<strong>Cologne</strong>')
 *     ->setIconUrl('https://example.com/waypoint.png');
 * ```
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Forms
 */
class MapWaypoint
{

    /**
     * Latitude of the waypoint.
     */
    protected float $latitude;

    /**
     * Longitude of the waypoint.
     */
    protected float $longitude;

    /**
     * Optional tooltip / title shown on hover.
     */
    protected string $title = '';

    /**
     * Optional HTML content shown in an info window on click.
     */
    protected string $infoWindow = '';

    /**
     * Optional custom icon URL. Leave empty to use the provider default.
     */
    protected string $iconUrl = '';

    /**
     * Optional icon width in pixels.
     */
    protected int $iconWidth = 0;

    /**
     * Optional icon height in pixels.
     */
    protected int $iconHeight = 0;

    /**
     * @param float $latitude
     * @param float $longitude
     */
    public function __construct(float $latitude, float $longitude)
    {
        $this->latitude  = $latitude;
        $this->longitude = $longitude;
    }

    /**
     * Static factory method for a fluent API.
     *
     * @param float $latitude
     * @param float $longitude
     * @return static
     */
    public static function create(float $latitude, float $longitude): static
    {
        return new static($latitude, $longitude);
    }

    /**
     * Sets the tooltip / title shown when hovering over the waypoint marker.
     *
     * @param string $title
     * @return static
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Sets the HTML content shown in the info window when the waypoint is clicked.
     *
     * @param string $html
     * @return static
     */
    public function setInfoWindow(string $html): static
    {
        $this->infoWindow = $html;
        return $this;
    }

    /**
     * Sets a custom icon URL for the waypoint marker.
     *
     * @param string $url    Absolute URL to an image file.
     * @param int    $width  Icon width in pixels (optional, 0 = provider default).
     * @param int    $height Icon height in pixels (optional, 0 = provider default).
     * @return static
     */
    public function setIconUrl(string $url, int $width = 0, int $height = 0): static
    {
        $this->iconUrl    = $url;
        $this->iconWidth  = $width;
        $this->iconHeight = $height;
        return $this;
    }

    /**
     * Returns the waypoint as an array for JSON serialisation.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'lat'        => $this->latitude,
            'lng'        => $this->longitude,
            'title'      => $this->title,
            'infoWindow' => $this->infoWindow,
            'iconUrl'    => $this->iconUrl,
            'iconWidth'  => $this->iconWidth,
            'iconHeight' => $this->iconHeight,
        ];
    }

}

