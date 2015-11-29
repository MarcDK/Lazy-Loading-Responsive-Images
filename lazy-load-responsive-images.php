<?php
defined('ABSPATH') or die("Nothing to see!");
/*
  Plugin Name: Lazy Loading Responsive Images
  Plugin URI: https://florianbrinkmann.de/1122/responsive-images-und-lazy-loading-in-wordpress/
  Description: Lazy loading Images plugin that works with responsive images.
  Version: 1.0
  Author: Florian Brinkmann, MarcDK
  Author URI: http://www.marc.tv
  License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.
  your option) any later version.

  This software uses the galleria http://galleria.io framework which uses the MIT License.
  The license is also GPL-compatible, meaning that the GPL permits combination
  and redistribution with software that uses the MIT License.
 */


function lazy_load_responsive_images($content)
{
    if (empty($content)) {
        return $content;
    }

    /* content should always be utf-8. Not setting this attribute results in iso encoding on most installations. */
    $dom = new DOMDocument("1.0", "utf-8");
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();

    /* the first image in a post should always be loaded to prevent visual glitches. */
    $isFirst = true;

    foreach ($dom->getElementsByTagName('img') as $img) {

        /* skip the first image. */
        if ($isFirst) {
            $isFirst = false;
            continue;
        }
        if ($img->hasAttribute('sizes') && $img->hasAttribute('srcset')) {

            $sizes_attr = $img->getAttribute('sizes');
            $srcset = $img->getAttribute('srcset');
            $img->setAttribute('data-sizes', $sizes_attr);
            $img->setAttribute('data-srcset', $srcset);
            $img->removeAttribute('sizes');
            $img->removeAttribute('srcset');
            $src = $img->getAttribute('src');
            if (!$src) {
                $src = $img->getAttribute('data-noscript');
            }
        } else {
            $src = $img->getAttribute('src');
            if (!$src) {
                $src = $img->getAttribute('data-noscript');
            }
            $img->setAttribute('data-src', $src);
        }
        $classes = $img->getAttribute('class');
        $classes .= " lazyload";
        $img->setAttribute('class', $classes);
        $img->removeAttribute('src');
        $noscript = $dom->createElement('noscript');
        $noscript_node = $img->parentNode->insertBefore($noscript, $img);
        $noscript_img = $dom->createElement('IMG');
        $classes = str_replace('lazyload', '', $classes);
        $noscript_img->setAttribute('class', $classes);
        $new_img = $noscript_node->appendChild($noscript_img);
        $new_img->setAttribute('src', $src);
        $content = $dom->saveHTML();
    }

    return $content;
}

add_filter('the_content', 'lazy_load_responsive_images', 20);

function lazy_load_responsive_images_modify_post_thumbnail_attr($attr, $attachment, $size)
{
    if (isset($attr['sizes'])) {
        $data_sizes = $attr['sizes'];
        unset($attr['sizes']);
        $attr['data-sizes'] = $data_sizes;
    }

    if (isset($attr['srcset'])) {
        $data_srcset = $attr['srcset'];
        unset($attr['srcset']);
        $attr['data-srcset'] = $data_srcset;
        $attr['data-noscript'] = $attr['src'];
        unset($attr['src']);
    }

    $attr['class'] .= ' lazyload';

    return $attr;
}

add_filter('wp_get_attachment_image_attributes', 'lazy_load_responsive_images_modify_post_thumbnail_attr', 20, 3);

function lazy_load_responsive_images_filter_post_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr)
{
    $dom = new DOMDocument("1.0", "utf-8");
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();

    foreach ($dom->getElementsByTagName('img') as $img) {
        $src = $img->getAttribute('data-noscript');
        $classes = $img->getAttribute('class');
    }

    $classes = str_replace('lazyload', '', $classes);
    $noscript_element = "<noscript><img src='" . $src . "' class='" . $classes . "'></noscript>";
    $html .= $noscript_element;

    return $html;
}

add_filter('post_thumbnail_html', 'lazy_load_responsive_images_filter_post_thumbnail_html', 10, 5);

function lazy_load_responsive_images_script()
{
    wp_enqueue_script('lazy_load_responsive_images_script-lazysizes', plugins_url() . '/lazy-load-responsive-images/js/lazysizes.js', '', false, false);
    wp_enqueue_style('lazy_load_responsive_images_style', plugins_url() . '/lazy-load-responsive-images/css/lazy_load_responsive_images.css');
}

add_action('wp_enqueue_scripts', 'lazy_load_responsive_images_script', 20, 0);