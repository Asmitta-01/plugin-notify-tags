
<?php

/**
 * -------------------------------------------------------------------------
 * NotifyTags plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2026 by the NotifyTags plugin team.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/notifytags
 * -------------------------------------------------------------------------
 */

/**
 * Plugin install process
 */
function plugin_notifytags_install(): bool
{
    return true;
}

/**
 * Plugin uninstall process
 */
function plugin_notifytags_uninstall(): bool
{
    return true;
}

function plugin_notifytags_register_tags($target): void
{
    $target->addTagToList([
        'tag'    => 'ticket.textcontent',
        'label'  => __('Ticket content (text only)', 'notifytags'),
        'value'  => true,
        'events' => NotificationTarget::TAG_FOR_ALL_EVENTS,
    ]);

    $target->addTagToList([
        'tag'    => 'ticket.contentfixed',
        'label'  => __('Ticket content with fixed images sizes', 'notifytags'),
        'value'  => true,
        'events' => NotificationTarget::TAG_FOR_ALL_EVENTS,
    ]);
}

function plugin_notifytags_get_img_dimension(string $attrs, string $name): ?int
{
    // width="123" / width='123' / width=123
    if (preg_match(
        '/\b' . preg_quote($name, '/') . '\s*=\s*(?:"(\d+)(?:px)?"|\'(\d+)(?:px)?\'|(\d+))/i',
        $attrs,
        $m
    )) {
        return (int)($m[1] ?: $m[2] ?: $m[3]);
    }

    // style="width:123px" / style="height:45px"
    if (preg_match(
        '/style\s*=\s*(["\']).*?\b' . preg_quote($name, '/') . '\s*:\s*(\d+)px.*?\1/i',
        $attrs,
        $m
    )) {
        return (int)$m[2];
    }

    // Si on cherche width, on peut aussi essayer max-width
    if ($name === 'width' && preg_match(
        '/style\s*=\s*(["\']).*?\bmax-width\s*:\s*(\d+)px.*?\1/i',
        $attrs,
        $m
    )) {
        return (int)$m[2];
    }

    return null;
}

function plugin_notifytags_is_likely_logo(string $attrs): bool
{
    $decoded = html_entity_decode($attrs, ENT_QUOTES, 'UTF-8');

    return preg_match(
        '/\b(?:logo|signature|sign|icon|avatar|badge|emoji|smiley)\b/i',
        $decoded
    ) === 1;
}

function plugin_notifytags_item_get_data($target): void
{
    if (!($target instanceof NotificationTargetTicket)) {
        return;
    }

    // Initialise toujours les tags pour éviter les warnings
    $target->data['##ticket.textcontent##']  = '';
    $target->data['##ticket.contentfixed##'] = '';

    if (!isset($target->obj) || !($target->obj instanceof Ticket)) {
        return;
    }

    $html_content = $target->obj->fields['content'] ?? '';
    if ($html_content === '') {
        return;
    }

    /**
     * 1) Version HTML : supprime les petites images / logos,
     *    laisse les images ~400px,
     *    redimensionne les grosses à 400px.
     */
    $contentWithFilteredImages = preg_replace_callback(
        '/<img\b([^>]*)>/is',
        function ($matches) {
            $attrs = $matches[1];

            $width  = plugin_notifytags_get_img_dimension($attrs, 'width');
            $height = plugin_notifytags_get_img_dimension($attrs, 'height');

            // Réglages
            $small_limit   = 120; // petite image / logo
            $near_min      = 340; // "proche de 400"
            $near_max      = 460;

            /**
             * 1.1 Supprime les logos / signatures probables
             */
            if (plugin_notifytags_is_likely_logo($attrs)) {
                return '';
            }

            /**
             * 1.2 Supprime les petites images
             * - si width connue et <= 120
             * - ou si height connue et <= 120
             * - ou si les 2 sont petites
             */
            if (
                ($width !== null && $width <= $small_limit) ||
                ($height !== null && $height <= $small_limit) ||
                ($width !== null && $height !== null && max($width, $height) <= 150)
            ) {
                return '';
            }

            /**
             * 1.3 Si l'image est déjà proche de 400px : on ne la touche pas
             */
            if ($width !== null && $width >= $near_min && $width <= $near_max) {
                return $matches[0];
            }

            /**
             * 1.4 Sinon on supprime width / height / style existants
             *     puis on impose 400px
             */
            $attrs = preg_replace(
                '/\s+(?:width|height)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
                '',
                $attrs
            );

            $attrs = preg_replace(
                '/\s+style\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
                '',
                $attrs
            );

            return '<img' . $attrs . ' width="400" style="width:400px; max-width:100%; height:auto; display:block;">';
        },
        html_entity_decode($html_content, ENT_QUOTES | ENT_HTML5, 'UTF-8')
    );

    // Nettoyage des wrappers vides après suppression d'images
    $contentWithFilteredImages = preg_replace('/<figure\b[^>]*>\s*<\/figure>/is', '', $contentWithFilteredImages);
    $contentWithFilteredImages = preg_replace('/<a\b[^>]*>\s*<\/a>/is', '', $contentWithFilteredImages);
    $target->data['##ticket.contentfixed##'] =
        '<div style="max-width:100%; overflow:hidden;">'
        . $contentWithFilteredImages
        . '</div>';

    /**
     * 2) Version TEXTE :
     *    on retire complètement les images restantes
     */
    $plain_text = \Glpi\RichText\RichText::getTextFromHtml(
        $html_content,
        preserve_case: true,
    );

    $with_media_links = preg_replace_callback(
        '/\[([^\]\r\n]+)\]\s*\[([^\]\r\n]+)\]/',
        function ($matches) {
            $name = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $url  = trim(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            // On ne transforme que les vrais liens de média GLPI
            if (
                !preg_match('~^https?://~i', $url) ||
                stripos($url, 'document.send.php') === false
            ) {
                return $matches[0];
            }

            $safe_name = htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $safe_url  = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return '<a href="' . $safe_url . '">Media: ' . $safe_name . '</a>';
        },
        $plain_text
    );

    $target->data['##ticket.textcontent##'] = nl2br($with_media_links);
}
