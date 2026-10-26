<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support\Blocks;

/**
 * Groups a block's schema into collapsible sections with
 * optional pair rendering. Pure data transformation: no view
 * calls, no HTML. The caller (block-config.php or show.php)
 * loops the returned sections, builds the field HTML via
 * _config-field.php, and passes the result to _config-section.php.
 */
final class ConfigGroup
{
    /**
     * @param array<string,array<string,mixed>> $schema
     * @param array<string,mixed>               $config
     * @param array<int,mixed>                  $categories
     * @return array<int, array{
     *     section: string,
     *     title: string,
     *     icon: string,
     *     count: int,
     *     fields: array<int, array{name:string, meta:array<string,mixed>, value:mixed}>
     * }>
     */
    public static function group(array $schema, array $config, array $categories = []): array
    {
        $meta = [
            'content'  => ['title' => 'Content',  'icon' => 'bi-text-paragraph'],
            'layout'   => ['title' => 'Layout',   'icon' => 'bi-layout-three-columns'],
            'style'    => ['title' => 'Style',    'icon' => 'bi-palette'],
            'advanced' => ['title' => 'Advanced', 'icon' => 'bi-gear'],
        ];

        $bySection = ['content' => [], 'layout' => [], 'style' => [], 'advanced' => []];
        foreach ($schema as $name => $fieldMeta) {
            $sec = (string) ($fieldMeta['section'] ?? 'content');
            if (!isset($bySection[$sec])) {
                $sec = 'content';
            }
            $bySection[$sec][$name] = $fieldMeta;
        }

        $sections = [];
        foreach ($bySection as $key => $fields) {
            if ($fields === []) {
                continue;
            }

            $flat = [];
            $seenPairs = [];
            foreach ($fields as $name => $fieldMeta) {
                $pairKey = (string) ($fieldMeta['pair'] ?? '');
                if ($pairKey === '') {
                    $flat[] = [
                        'name'  => $name,
                        'meta'  => $fieldMeta,
                        'value' => $config[$name] ?? ($fieldMeta['default'] ?? ''),
                    ];
                    continue;
                }
                if (in_array($pairKey, $seenPairs, true)) {
                    continue;
                }
                $seenPairs[] = $pairKey;
                // Collect every field with this pair key as a group.
                $pairFields = [];
                foreach ($fields as $n => $m) {
                    if (((string) ($m['pair'] ?? '')) === $pairKey) {
                        $pairFields[] = [
                            'name'  => $n,
                            'meta'  => $m,
                            'value' => $config[$n] ?? ($m['default'] ?? ''),
                        ];
                    }
                }
                $flat[] = [
                    'pair'   => $pairKey,
                    'fields' => $pairFields,
                ];
            }

            $sections[] = [
                'section' => $key,
                'title'   => $meta[$key]['title'],
                'icon'    => $meta[$key]['icon'],
                'count'   => count($fields),
                'fields'  => $flat,
            ];
        }

        return $sections;
    }
}
