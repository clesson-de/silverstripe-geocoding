<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Forms;

/**
 * Represents a route to display on a MapField.
 *
 * A route consists of a start point, an end point, optional intermediate
 * waypoints, and optional visual settings (line colour, weight).
 *
 * Routes are purely decorative — they are not saved when the form is submitted.
 *
 * Usage:
 * ```php
 * $route = MapRoute::create(
 *     MapWaypoint::create(52.5200, 13.4050)->setTitle('Berlin'),
 *     MapWaypoint::create(48.1351, 11.5820)->setTitle('Munich')
 * )
 *     ->addWaypoint(MapWaypoint::create(50.9333, 6.9500)->setTitle('Cologne'))
 *     ->setColor('#e74c3c')
 *     ->setWeight(4);
 *
 * $mapField->setRoute($route);
 * ```
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Forms
 */
class MapRoute
{

    /**
     * Route start point.
     */
    protected MapWaypoint $origin;

    /**
     * Route end point.
     */
    protected MapWaypoint $destination;

    /**
     * Intermediate waypoints.
     *
     * @var MapWaypoint[]
     */
    protected array $waypoints = [];

    /**
     * Polyline colour (CSS hex colour, e.g. '#3388ff').
     */
    protected string $color = '#3388ff';

    /**
     * Polyline stroke weight in pixels.
     */
    protected int $weight = 3;

    /**
     * Polyline opacity (0.0 – 1.0).
     */
    protected float $opacity = 1.0;

    /**
     * Whether to draw a straight line between points instead of a routed path.
     * When true, no routing service is called — the line is drawn directly.
     */
    protected bool $straightLine = false;

    /**
     * @param MapWaypoint $origin
     * @param MapWaypoint $destination
     */
    public function __construct(MapWaypoint $origin, MapWaypoint $destination)
    {
        $this->origin      = $origin;
        $this->destination = $destination;
    }

    /**
     * Static factory method for a fluent API.
     *
     * @param MapWaypoint $origin
     * @param MapWaypoint $destination
     * @return static
     */
    public static function create(MapWaypoint $origin, MapWaypoint $destination): static
    {
        return new static($origin, $destination);
    }

    /**
     * Adds an intermediate waypoint to the route.
     *
     * @param MapWaypoint $waypoint
     * @return static
     */
    public function addWaypoint(MapWaypoint $waypoint): static
    {
        $this->waypoints[] = $waypoint;
        return $this;
    }

    /**
     * Sets the polyline colour.
     *
     * @param string $color CSS hex colour, e.g. '#e74c3c'
     * @return static
     */
    public function setColor(string $color): static
    {
        $this->color = $color;
        return $this;
    }

    /**
     * Sets the polyline stroke weight in pixels.
     *
     * @param int $weight
     * @return static
     */
    public function setWeight(int $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * Sets the polyline opacity (0.0 – 1.0).
     *
     * @param float $opacity
     * @return static
     */
    public function setOpacity(float $opacity): static
    {
        $this->opacity = max(0.0, min(1.0, $opacity));
        return $this;
    }

    /**
     * When set to true, draws a straight line between points instead of a routed path.
     *
     * @param bool $straight
     * @return static
     */
    public function setStraightLine(bool $straight): static
    {
        $this->straightLine = $straight;
        return $this;
    }

    /**
     * Returns the route as an array for JSON serialisation.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'origin'      => $this->origin->toArray(),
            'destination' => $this->destination->toArray(),
            'waypoints'   => array_map(fn(MapWaypoint $w) => $w->toArray(), $this->waypoints),
            'color'       => $this->color,
            'weight'      => $this->weight,
            'opacity'     => $this->opacity,
            'straightLine'=> $this->straightLine,
        ];
    }

}

