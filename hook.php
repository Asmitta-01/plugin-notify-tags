
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
}

function plugin_notifytags_item_get_data($target): void
{
    if (!($target instanceof NotificationTargetTicket)) {
        return;
    }

    $html_content = $target->data['##ticket.description##'] ?? '';
    if ($html_content === '') {
        return;
    }

    // Remove <figure> elements that wrap a single image (leave no empty blocks behind)
    $without_images = preg_replace('/<figure[^>]*>\s*<img[^>]*\/?>\s*<\/figure>/is', '', $html_content);
    // Remove any remaining standalone <img> tags
    $without_images = preg_replace('/<img[^>]*\/?>/is', '', $without_images);

    $target->data['##ticket.textcontent##'] = \Glpi\RichText\RichText::getTextFromHtml(
        $without_images,
        preserve_case: true,
    );
}
