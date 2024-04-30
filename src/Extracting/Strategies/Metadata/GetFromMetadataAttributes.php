<?php

namespace Knuckles\Scribe\Extracting\Strategies\Metadata;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Subgroup;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Strategies\PhpAttributeStrategy;
use Str;

/**
 * @extends PhpAttributeStrategy<Group|Subgroup|Endpoint|Authenticated>
 */
class GetFromMetadataAttributes extends PhpAttributeStrategy
{
    use ParamHelpers;

    protected static array $attributeNames = [
        Group::class,
        Subgroup::class,
        Endpoint::class,
        Authenticated::class,
        Unauthenticated::class,
    ];

    private function getGroupMetadataFromConfig(ExtractedEndpointData $endpointData, array $metadata): array
    {
        foreach ($this->config->get('groups')['list'] as $group) {
            foreach ($group['endpoints'] as $pathToMatch) {
                if (Str::is($pathToMatch, $endpointData->uri)) {
                    $metadata['groupName'] = $group['name'];
                    $metadata['groupDescription'] = $group['description'];
                    break 2;
                }
            }
        }

        return $metadata;
    }

    protected function extractFromAttributes(
        ExtractedEndpointData $endpointData,
        array $attributesOnMethod,
        array $attributesOnFormRequest = [],
        array $attributesOnController = []
    ): ?array {
        $metadata = [
            'groupName' => '',
            'groupDescription' => '',
            'subgroup' => '',
            'subgroupDescription' => '',
            'title' => '',
            'description' => '',
        ];
        foreach ([...$attributesOnController, ...$attributesOnFormRequest, ...$attributesOnMethod] as $attributeInstance) {
            $metadata = array_merge($metadata, $attributeInstance->toArray());
        }
        if ($metadata['groupName'] == '') {
            $metadata = $this->getGroupMetadataFromConfig($endpointData, $metadata);
        }

        return $metadata;
    }
}
