<?php

/**
 * Generate Image from GeoJSON
 *
 * @copyright 2013-2013 Ronny Wang <ronnywang at gmail.com>
 * @license BSD License http://opensource.org/licenses/BSD-3-Clause
 * 
 */
class GeoJSON2Image
{
    /**
     * get boundry from 2 boundies
     * 
     * @param array $b1 boundry
     * @param array $b2 boundry
     * @access protected
     * @return array boundry
     */
    protected static function computeBoundry($b1, $b2)
    {
        if (is_null($b1)) {
            return $b2;
        }
        return array(
            min($b1[0], $b2[0]),
            max($b1[1], $b2[1]),
            min($b1[2], $b2[2]),
            max($b1[3], $b2[3]),
        );
    }

    /**
     * get boundry from geojson
     * 
     * @param object $json 
     * @access public
     * @return array(minx, maxx, miny, maxy)
     */
    public static function getBoundry($json)
    {
        switch ($json->type) {
        case 'GeometryCollection':
            $return_boundry = null;
            foreach ($json->geometries as $geometry) {
                $return_boundry = self::computeBoundry($return_boundry, self::getBoundry($geometry));
            }
            return $return_boundry;

        case 'FeatureCollection':
            $return_boundry = null;
            foreach ($json->features as $feature) {
                $return_boundry = self::computeBoundry($return_boundry, self::getBoundry($feature));
            }
            return $return_boundry;

        case 'Feature':
            return self::getBoundry($json->geometry);

        case 'Point':
            return array($json->coordinates[0], $json->coordinates[0], $json->coordinates[1], $json->coordinates[1]);

        case 'MultiPoint':
            $return_boundry = null;
            foreach ($json->coordinates as $point) {
                $return_boundry = self::computeBoundry($return_boundry, array($point[0], $point[0], $point[1], $point[1]));
            }
            return $return_boundry;

        case 'LineString':
            $return_boundry = null;
            foreach ($json->coordinates as $point) {
                $return_boundry = self::computeBoundry($return_boundry, array($point[0], $point[0], $point[1], $point[1]));
            }
            return $return_boundry;

        case 'MultiLineString':
            $return_boundry = null;
            foreach ($json->coordinates as $linestrings) {
                foreach ($linestrings as $point) {
                    $return_boundry = self::computeBoundry($return_boundry, array($point[0], $point[0], $point[1], $point[1]));
                }
            }
            return $return_boundry;

        case 'Polygon':
            $return_boundry = null;
            foreach ($json->coordinates as $linestrings) {
                foreach ($linestrings as $point) {
                    $return_boundry = self::computeBoundry($return_boundry, array($point[0], $point[0], $point[1], $point[1]));
                }
            }
            return $return_boundry;

        case 'MultiPolygon':
            $return_boundry = null;
            foreach ($json->coordinates as $polygons) {
                foreach ($polygons as $linestrings) {
                    foreach ($linestrings as $point) {
                        $return_boundry = self::computeBoundry($return_boundry, array($point[0], $point[0], $point[1], $point[1]));
                    }
                }
            }
            return $return_boundry;
        default:
            throw new Exception("Unsupported GeoJSON type:{$json->type}");
        }
    }

    protected static function pixelX($x)
    {
        return ($x + 180) / 360;
    }

    protected static function pixelY($y)
    {
        $sin_y = sin($y * pi() / 180);
        return (0.5 - log((1 + $sin_y) / (1 - $sin_y)) / (4 * pi()));
    }

    /**
     * Tranfrom geojson coordinates to image coordinates
     * 
     * @param array $point 
     * @param array $boundry 
     * @param int $max_size 
     * @static
     * @access public
     * @return void
     */
    public static function transformPoint($point, $boundry, $max_size)
    {
        if ($point[0] == 180 or $point[0] == -180) {
            return false;
        }
        $x_delta = self::pixelX($boundry[1]) - self::pixelX($boundry[0]);
        $y_delta = self::pixelY($boundry[3]) - self::pixelY($boundry[2]);

        $new_point = array();
        $p = function($a){
            $a += 180;
            if ($a > 360) {
                $a -= 360;
            }
            return $a;
        };
        $new_point[0] = floor((self::pixelX($p($point[0]) + $boundry[4]) - self::pixelX($p($boundry[0]) + $boundry[4])) * $max_size / $x_delta);
        $new_point[1] = floor((self::pixelY($boundry[3]) - self::pixelY($point[1])) * $max_size / $y_delta);
        return $new_point;
        $x_delta = $boundry[1] - $boundry[0];
        $y_delta = $boundry[3] - $boundry[2];

        return array(
            ($point[0] - $boundry[0]) * $max_size / $x_delta,
            ($boundry[3] - $point[1]) * $max_size / $y_delta,
        );
    }

    /**
     * draw the GeoJSON on image
     * 
     * @param Image $gd 
     * @param object $json 
     * @param array $boundry 
     * @param int $max_size 
     * @param array $draw_options : background_color : array(r,g,b)
     * @static
     * @access public
     * @return void
     */
    public static function drawJSON($gd, $json, $boundry, $max_size, $draw_options = array())
    {
        $x_delta = $boundry[1] - $boundry[0];
        $y_delta = $boundry[3] - $boundry[2];
        $max_delta = max($x_delta, $y_delta);

        switch ($json->type) {
        case 'GeometryCollection':
            foreach ($json->geometries as $geometry) {
                self::drawJSON($gd, $geometry, $boundry, $max_size, $draw_options);
            }
            break;

        case 'FeatureCollection':
            foreach ($json->features as $feature) {
                self::drawJSON($gd, $feature, $boundry, $max_size);
            }
            break;

        case 'Feature':
            self::drawJSON($gd, $json->geometry, $boundry, $max_size, (array)($json->properties));
            break;

        case 'MultiPolygon':
            if (array_key_exists('background_color', $draw_options)) {
                $color = imagecolorallocate($gd, $draw_options['background_color'][0], $draw_options['background_color'][1], $draw_options['background_color'][2]);
            } else {
                // random color if no background_color
                $color = imagecolorallocate($gd, rand(0, 255), rand(0, 255), rand(0, 255));
            }

            foreach ($json->coordinates as $polygons) {
                foreach ($polygons as $linestrings) {
                    $points = array();
                    if (count($linestrings) <= 3) {
                        // skip 2 points
                        continue 2;
                    }
                    foreach ($linestrings as $point) {
                        $new_point = self::transformPoint($point, $boundry, $max_size);
                        $points[] = floor($new_point[0]);
                        $points[] = floor($new_point[1]);
                    }
                    imagefilledpolygon($gd, $points, count($points) / 2, $color);
                }
            }
            break;

        case 'Point':
        case 'MultiPoint':
        case 'LineString':
        case 'MultiLineString':
        case 'Polygon':
        default:
            throw new Exception("Unsupported GeoJSON type:{$json->type}");
        }

    }

    /**
     * get GD Image From GeoJSON
     * 
     * @param object $json 
     * @param int $max_size
     * @access public
     * @return object image: GD object
     *                boundry: boundry
     */
    public static function json2image($json, $max_size = 2000)
    {
        // 先找到長寬
        $boundry = self::getBoundry($json);

        $x_delta = $boundry[1] - $boundry[0];
        $y_delta = $boundry[3] - $boundry[2];
        $max_delta = max($x_delta, $y_delta);
        $gd = imagecreatetruecolor(
            floor($max_size * $x_delta / $max_delta),
            floor($max_size * $y_delta / $max_delta)
        );
        $bg_color = imagecolorallocate($gd, 0, 0, 0);
        imagecolortransparent($gd, $bg_color);
        self::drawJSON($gd, $json, $boundry, $max_size);
        return array(
            'image' => $gd,
            'boundry' => $boundry,
        );
    }
}

