<?php

namespace GlpiPlugin\NotifyTags;


class Logo
{
    public static function createIfMissing(): int
    {
        global $DB;

        $existing = $DB->request([
            'FROM'  => 'glpi_documents',
            'WHERE' => ['name' => 'NotifyTags Logo'],
        ])->current();

        if ($existing) {
            return (int) $existing['id'];
        }

        $sourcePath = __DIR__ . '/../public/img/logo.png';
        $tmpName    = uniqid('notifytags_logo_') . '.png';
        $tmpPath    = GLPI_TMP_DIR . '/' . $tmpName;

        copy($sourcePath, $tmpPath);

        $document = new \Document();
        $docId = $document->add([
            'name'         => 'NotifyTags Logo',
            'entities_id'  => 0,
            'is_recursive' => 1,
            '_filename'    => [$tmpName],
        ]);

        return (int) $docId;
    }

    public static function getDocId(): int
    {
        global $DB;

        $row = $DB->request([
            'FROM'  => 'glpi_documents',
            'WHERE' => ['name' => 'NotifyTags Logo'],
        ])->current();

        return $row ? (int) $row['id'] : 0;
    }
}
