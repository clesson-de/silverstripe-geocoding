<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Configurable;
use Throwable;

/**
 * Generates a static map PNG image by stitching OSM tile images server-side.
 *
 * This controller avoids reliance on any third-party static map API.
 * It fetches individual map tiles from a configurable tile server and
 * composites them into a single image using PHP's GD extension.
 * A red circular marker is drawn at the given coordinates.
 *
 * Route (defined in _config/routes.yml):
 *   GET /geocoding-static-map?lat=48.1351&lng=11.5820&zoom=14&width=400&height=300
 *
 * The response is a PNG image with a one-hour Cache-Control header so that
 * repeated requests for the same coordinates are served from the browser cache.
 *
 * Configuration (override in project _config/config.yml):
 * ```yaml
 * Clesson\Silverstripe\Geocoding\Controllers\StaticMapController:
 *   tile_url:    'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
 *   user_agent:  'silverstripe-geocoding/1.0'
 *   max_width:   800
 *   max_height:  600
 *   max_zoom:    18
 * ```
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Controllers
 */
class StaticMapController extends Controller
{
    use Configurable;

    /**
     * OSM tile URL template used as the default layer.
     * Placeholders: {z}, {x}, {y}.
     *
     * @config
     */
    private static string $tile_url = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

    /**
     * Named tile layers available via the `layer` query parameter.
     *
     * Keys are the layer identifiers accepted in the URL.
     * Values are tile URL templates with {z}, {x}, {y} placeholders.
     *
     * Extend this list in your project config:
     * ```yaml
     * Clesson\Silverstripe\Geocoding\Controllers\StaticMapController:
     *   layers:
     *     satellite: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
     * ```
     *
     * @config
     */
    private static array $layers = [
        'osm'      => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        'topo'     => 'https://tile.opentopomap.org/{z}/{x}/{y}.png',
        'cyclosm'  => 'https://tile.cyclosm.org/cyclosm/{z}/{x}/{y}.png',
        'humanitarian' => 'https://a.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
    ];

    /**
     * User-Agent header sent with tile requests.
     * The OSM tile usage policy requires a descriptive User-Agent.
     *
     * @config
     */
    private static string $user_agent = 'silverstripe-geocoding/1.0 (static-map-thumbnail)';

    /**
     * Maximum allowed image width in pixels.
     *
     * @config
     */
    private static int $max_width = 800;

    /**
     * Maximum allowed image height in pixels.
     *
     * @config
     */
    private static int $max_height = 600;

    /**
     * Maximum allowed zoom level.
     *
     * @config
     */
    private static int $max_zoom = 18;

    /**
     * @inheritdoc
     */
    private static array $allowed_actions = [
        'index',
    ];

    /**
     * Allows unauthenticated access so that browsers can load the thumbnail
     * image via a plain <img src="..."> tag without a CMS session.
     *
     * The endpoint only accepts lat/lng/zoom/width/height query parameters
     * and returns image data — no sensitive information is exposed.
     *
     * @param mixed $member
     * @return bool
     */
    public function canView($member = null): bool
    {
        return true;
    }

    /**
     * Renders a static map PNG for the given coordinates.
     *
     * URL example: /geocoding-static-map?lat=48.1351&lng=11.5820&zoom=14&width=400&height=300&layer=topo
     *
     * Query parameters:
     * - lat    (float,  required)             Latitude of the map center / marker
     * - lng    (float,  required)             Longitude of the map center / marker
     * - zoom   (int,    optional, default 14) Zoom level 1–18
     * - width  (int,    optional, default 400) Output width in pixels
     * - height (int,    optional, default 300) Output height in pixels
     * - layer  (string, optional, default '') Layer key from the `layers` config, e.g. 'topo'.
     *                                         Falls back to the `tile_url` config when empty or unknown.
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $lat    = (float) $request->getVar('lat');
        $lng    = (float) $request->getVar('lng');
        $zoom   = min((int) ($request->getVar('zoom') ?: 14), self::config()->get('max_zoom'));
        $width  = min((int) ($request->getVar('width') ?: 400), self::config()->get('max_width'));
        $height = min((int) ($request->getVar('height') ?: 300), self::config()->get('max_height'));
        $layer  = (string) ($request->getVar('layer') ?: '');

        if (!$lat || !$lng) {
            return $this->errorResponse('Missing lat or lng parameter.');
        }

        $zoom   = max(1, $zoom);
        $width  = max(64, $width);
        $height = max(64, $height);

        // Resolve tile URL: named layer → config fallback
        $layers  = self::config()->get('layers') ?? [];
        $tileUrl = (isset($layers[$layer]) && $layers[$layer])
            ? $layers[$layer]
            : self::config()->get('tile_url');

        try {
            $png = $this->buildImage($lat, $lng, $zoom, $width, $height, $tileUrl);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to build map image: ' . $e->getMessage());
        }

        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'image/png');
        $response->addHeader('Cache-Control', 'public, max-age=3600');
        $response->setBody($png);

        return $response;
    }

    /**
     * Builds the composite PNG image from OSM-compatible tiles.
     *
     * @param float  $lat
     * @param float  $lng
     * @param int    $zoom
     * @param int    $width
     * @param int    $height
     * @param string $tileUrl Tile URL template with {z}, {x}, {y} placeholders.
     * @return string Raw PNG binary string
     * @throws \RuntimeException when GD is not available or tile fetching fails
     */
    protected function buildImage(float $lat, float $lng, int $zoom, int $width, int $height, string $tileUrl = ''): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('PHP GD extension is not available.');
        }

        if (!$tileUrl) {
            $tileUrl = self::config()->get('tile_url');
        }

        // Convert lat/lng to fractional tile coordinates at the given zoom level
        $tileX = $this->lngToTileX($lng, $zoom);
        $tileY = $this->latToTileY($lat, $zoom);

        // Tile size is always 256×256 pixels for OSM
        $tileSize = 256;

        // How many tiles do we need to cover the requested pixel dimensions?
        $tilesX = (int) ceil($width  / $tileSize) + 2;
        $tilesY = (int) ceil($height / $tileSize) + 2;

        // Integer tile indices of the top-left tile
        $startTileX = (int) floor($tileX) - (int) floor($tilesX / 2);
        $startTileY = (int) floor($tileY) - (int) floor($tilesY / 2);

        // Canvas for the full stitched tile grid
        $canvasW = $tilesX * $tileSize;
        $canvasH = $tilesY * $tileSize;

        $canvas = imagecreatetruecolor($canvasW, $canvasH);
        if ($canvas === false) {
            throw new \RuntimeException('Could not create GD canvas.');
        }

        $client = new Client(['timeout' => 5]);

        // Fetch and draw each tile
        for ($tx = 0; $tx < $tilesX; $tx++) {
            for ($ty = 0; $ty < $tilesY; $ty++) {
                $tileData = $this->fetchTile($client, $startTileX + $tx, $startTileY + $ty, $zoom, $tileUrl);
                if ($tileData === null) {
                    continue;
                }

                $tile = @imagecreatefromstring($tileData);
                if ($tile === false) {
                    continue;
                }

                imagecopy($canvas, $tile, $tx * $tileSize, $ty * $tileSize, 0, 0, $tileSize, $tileSize);
                imagedestroy($tile);
            }
        }

        // Calculate where the center coordinate falls on the canvas
        $offsetX = ($tileX - $startTileX) * $tileSize;
        $offsetY = ($tileY - $startTileY) * $tileSize;

        // Crop to the requested size centered on the coordinate
        $cropX = (int) round($offsetX - $width  / 2);
        $cropY = (int) round($offsetY - $height / 2);

        $output = imagecreatetruecolor($width, $height);
        if ($output === false) {
            throw new \RuntimeException('Could not create GD output image.');
        }

        imagecopy($output, $canvas, 0, 0, $cropX, $cropY, $width, $height);
        imagedestroy($canvas);

        // Draw marker at the center
        $this->drawMarker($output, (int) round($width / 2), (int) round($height / 2));

        // Capture PNG output
        ob_start();
        imagepng($output);
        $png = ob_get_clean();
        imagedestroy($output);

        return $png ?: '';
    }

    /**
     * Fetches a single tile as a raw PNG string.
     * Returns null on failure.
     *
     * @param Client $client
     * @param int    $x
     * @param int    $y
     * @param int    $zoom
     * @param string $tileUrl Tile URL template with {z}, {x}, {y} placeholders.
     * @return string|null
     */
    protected function fetchTile(Client $client, int $x, int $y, int $zoom, string $tileUrl): ?string
    {
        $maxTile = 2 ** $zoom;
        // Wrap tile coordinates (handles tiles at the map edge)
        $x = (($x % $maxTile) + $maxTile) % $maxTile;
        $y = (($y % $maxTile) + $maxTile) % $maxTile;

        $url = str_replace(['{z}', '{x}', '{y}'], [$zoom, $x, $y], $tileUrl);

        try {
            $response = $client->get($url, [
                'headers' => [
                    'User-Agent' => self::config()->get('user_agent'),
                    'Referer'    => 'https://www.openstreetmap.org/',
                ],
            ]);

            return (string) $response->getBody();
        } catch (GuzzleException) {
            return null;
        }
    }

    /**
     * Draws a red teardrop-shaped map pin on the image.
     *
     * @param \GdImage $image
     * @param int      $cx  X center of the pin anchor
     * @param int      $cy  Y center of the pin anchor
     * @return void
     */
    protected function drawMarker(\GdImage $image, int $cx, int $cy): void
    {
        $red    = imagecolorallocate($image, 220, 38, 38);
        $white  = imagecolorallocate($image, 255, 255, 255);
        $dark   = imagecolorallocate($image, 140, 20, 20);

        if ($red === false || $white === false || $dark === false) {
            return;
        }

        $r  = 10; // circle radius
        $py = $cy + $r + 8; // tip of the pin below the circle

        // Circle body
        imagefilledellipse($image, $cx, $cy - $r, $r * 2, $r * 2, $red);
        // Border
        imageellipse($image, $cx, $cy - $r, $r * 2, $r * 2, $dark);
        // Pin tip (filled triangle)
        imagefilledpolygon($image, [
            $cx - 6, $cy,
            $cx + 6, $cy,
            $cx, $py,
        ], $red);
        // White inner dot
        imagefilledellipse($image, $cx, $cy - $r, 6, 6, $white);
    }

    /**
     * Converts longitude to a fractional tile X coordinate.
     *
     * @param float $lng
     * @param int   $zoom
     * @return float
     */
    protected function lngToTileX(float $lng, int $zoom): float
    {
        return ($lng + 180.0) / 360.0 * (2 ** $zoom);
    }

    /**
     * Converts latitude to a fractional tile Y coordinate.
     *
     * @param float $lat
     * @param int   $zoom
     * @return float
     */
    protected function latToTileY(float $lat, int $zoom): float
    {
        $latRad = deg2rad($lat);
        return (1.0 - log(tan($latRad) + 1.0 / cos($latRad)) / M_PI) / 2.0 * (2 ** $zoom);
    }

    /**
     * Returns a plain-text error response with HTTP 400.
     *
     * @param string $message
     * @return HTTPResponse
     */
    protected function errorResponse(string $message): HTTPResponse
    {
        $response = HTTPResponse::create();
        $response->setStatusCode(400);
        $response->addHeader('Content-Type', 'text/plain');
        $response->setBody($message);

        return $response;
    }

}

