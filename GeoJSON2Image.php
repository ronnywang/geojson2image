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
     * @param int $width
     * @param int $height
     * @static
     * @access public
     * @return void
     */
    public static function transformPoint($point, $boundry, $width, $height)
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
        $new_point[0] = floor((self::pixelX($p($point[0]) + $boundry[4]) - self::pixelX($p($boundry[0]) + $boundry[4])) * $width / $x_delta);
        $new_point[1] = floor((self::pixelY($boundry[3]) - self::pixelY($point[1])) * $height / $y_delta);
        return $new_point;
        $x_delta = $boundry[1] - $boundry[0];
        $y_delta = $boundry[3] - $boundry[2];

        return array(
            ($point[0] - $boundry[0]) * $width / $x_delta,
            ($boundry[3] - $point[1]) * $height / $y_delta,
        );
    }

    /**
     * draw the GeoJSON on image
     * 
     * @param Image $gd 
     * @param object $json 
     * @param array $boundry 
     * @param int $width
     * @param int $height
     * @param array $draw_options : background_color : array(r,g,b)
     * @static
     * @access public
     * @return void
     */
    public static function drawJSON($gd, $json, $boundry, $width, $height, $draw_options = array())
    {
        $x_delta = $boundry[1] - $boundry[0];
        $y_delta = $boundry[3] - $boundry[2];
        $max_delta = max($x_delta, $y_delta);

        switch ($json->type) {
        case 'GeometryCollection':
            foreach ($json->geometries as $geometry) {
                self::drawJSON($gd, $geometry, $boundry, $width, $height, $draw_options);
            }
            break;

        case 'FeatureCollection':
            foreach ($json->features as $feature) {
                self::drawJSON($gd, $feature, $boundry, $width, $height);
            }
            break;

        case 'Feature':
            self::drawJSON($gd, $json->geometry, $boundry, $width, $height, (array)($json->properties));
            break;

        case 'Polygon':
            if (array_key_exists('polygon_background_color', $draw_options) and $draw_options['polygon_background_color'] !== false) {
                $background_color = imagecolorallocate($gd, $draw_options['polygon_background_color'][0], $draw_options['polygon_background_color'][1], $draw_options['polygon_background_color'][2]);
            } else {
                // no color if no polygon_background_color
                $background_color = null;
            }

            if (array_key_exists('polygon_border_color', $draw_options)) {
                $border_color = imagecolorallocate($gd, $draw_options['polygon_border_color'][0], $draw_options['polygon_border_color'][1], $draw_options['polygon_border_color'][2]);
            } else {
                $border_color = imagecolorallocate($gd, 0, 0, 0);
            }

            if (array_key_exists('polygon_border_size', $draw_options)) {
                $border_size = $draw_options['polygon_border_size'];
            } else {
                $border_size = 3;
            }

            $filled_points = array();
            foreach ($json->coordinates as $linestrings) {
                $border_points = array();
                if ($linestrings[0] != $linestrings[count($linestrings) - 1]) {
                    $linestrings[] = $linestrings[0];
                }
                if (count($linestrings) <= 3) {
                    // skip 2 points
                    continue 2;
                }
                foreach ($linestrings as $point) {
                    if (!$new_point = self::transformPoint($point, $boundry, $width, $height)) {
                        continue;
                    }
                    $border_points[] = floor($new_point[0]);
                    $filled_points[] = floor($new_point[0]);
                    $border_points[] = floor($new_point[1]);
                    $filled_points[] = floor($new_point[1]);
                }
                if (count($border_points) < 3) {
                    continue;
                }
                if ($border_size) {
                    imagesetthickness($gd, $border_size);
                    imagepolygon($gd, $border_points, count($border_points) / 2, $border_color);
                }
            }

            if (!is_null($background_color) and count($filled_points) >= 6) {
                imagefilledpolygon($gd, $filled_points, count($filled_points) / 2, $background_color);
            }
            break;

        case 'MultiPolygon':
            foreach ($json->coordinates as $polygon) {
                $j = new StdClass;
                $j->type = 'Polygon';
                $j->coordinates = $polygon;
                self::drawJSON($gd, $j, $boundry, $width, $height, $draw_options);
            }
            break;

        case 'Point':
            if (array_key_exists('point_background_color', $draw_options)) {
                $background_color = imagecolorallocate($gd, $draw_options['point_background_color'][0], $draw_options['point_background_color'][1], $draw_options['point_background_color'][2]);
            } else {
                // default point color is red
                $background_color = imagecolorallocate($gd, 255, 0, 0);
            }

            if (array_key_exists('point_border_size', $draw_options)) {
                $border_size = $draw_options['point_border_size'];
            } else {
                $border_size = 1;
            }

            $point_size = 10;

            if (array_key_exists('point_border_color', $draw_options)) {
                $border_color = imagecolorallocate($gd, $draw_options['point_border_color'][0], $draw_options['point_border_color'][1], $draw_options['point_border_color'][2]);
            } else {
                // default point border color is black
                $border_color = imagecolorallocate($gd, 0, 0, 0);
            }

            $point = $json->coordinates;
            $new_point = self::transformPoint($point, $boundry, $width, $height);
            imagefilledellipse($gd, $new_point[0], $new_point[1], $point_size, $point_size, $background_color);
            for ($i = 0; $i < $border_size; $i ++) {
                imageellipse($gd, $new_point[0], $new_point[1], $point_size - 1 + $i, $point_size - 1 + $i, $border_color);
            }
            break;

        case 'MultiPoint':
            foreach ($json->coordinates as $coordinate) {
                $j = new StdClass;
                $j->type = 'Point';
                $j->coordinates = $coordinate;
                self::drawJSON($gd, $j, $boundry, $width, $height, $draw_options);
            }
            break;

        case 'LineString':
            $last_point = null;

            if (array_key_exists('line_border_color', $draw_options)) {
                $border_color = imagecolorallocate($gd, $draw_options['line_border_color'][0], $draw_options['line_border_color'][1], $draw_options['line_border_color'][2]);
            } else {
                $border_color = imagecolorallocate($gd, 0, 0, 0);
            }
            if (array_key_exists('line_border_size', $draw_options)) {
                $border_size = $draw_options['line_border_size'];
            } else {
                $border_size = 3;
            }
            foreach ($json->coordinates as $point) {
                $new_point = self::transformPoint($point, $boundry, $width, $height);
                if (!is_null($last_point)) {
                    imagesetthickness($gd, $border_size);
                    imageline($gd, $last_point[0], $last_point[1], $new_point[0], $new_point[1], $border_color);
                }

                $last_point = $new_point;
            }
            break;

        case 'MultiLineString':
            foreach ($json->coordinates as $coordinate) {
                $j = new StdClass;
                $j->type = 'LineString';
                $j->coordinates = $coordinate;
                self::drawJSON($gd, $j, $boundry, $width, $height, $draw_options);
            }
            break;

        default:
            throw new Exception("Unsupported GeoJSON type:{$json->type}");
        }

    }

    public function GeoJSON2Image($json)
    {
        $this->json = $json;
    }

    protected $_boundry = null;

    public function setBoundry($boundry)
    {
        $this->_boundry = $boundry;
    }

    protected $_width = 400;
    protected $_height = 400;

    public function setSize($width, $height)
    {
        $this->_width = $width;
        $this->_height = $height;
    }

    public function draw($file = null)
    {
        // 先找到長寬
        $boundry = !is_null($this->_boundry) ? $this->_boundry : self::getBoundry($this->json);

        $gd = imagecreatetruecolor(
            $this->_width,
            $this->_height
        );
        $bg_color = imagecolorallocate($gd, 254, 254, 254);
        imagecolortransparent($gd, $bg_color);
        imagefill($gd, 0, 0, $bg_color);
        $boundry[4] = 0;
        if ($boundry[1] > $boundry[0]) {
            self::drawJSON($gd, $this->json, $boundry, $this->_width, $this->_height);
        } else {
            $boundry[1] += 360;
            self::drawJSON($gd, $this->json, $boundry, $this->_width, $this->_height);

            $boundry[1] -= 360;
            $boundry[0] -= 360;
            self::drawJSON($gd, $this->json, $boundry, $this->_width, $this->_height);
        }

        if (is_null($file)) {
            header('Content-Type: image/png');
            imagepng($gd);
        } else {
            imagepng($gd, $file);
        }
    }
}

