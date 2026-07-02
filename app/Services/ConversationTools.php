<?php

namespace App\Services;

class ConversationTools
{
    /**
     * Backend RPC tool declarations sent when creating a realtime session.
     * The Node relay registers a handler for each tool name and forwards
     * the call to the matching /relay/tools endpoint.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'type' => 'backend_rpc',
                'name' => 'get_weather',
                'description' => 'Look up the current weather for a city. Use when the child mentions a city, says where they live, or asks about the weather.',
                'timeoutSeconds' => 8,
                'parameters' => [
                    [
                        'type' => 'string',
                        'name' => 'city',
                        'description' => 'The city name, for example "Phoenix" or "Paris".',
                        'required' => true,
                    ],
                ],
            ],
            [
                'type' => 'backend_rpc',
                'name' => 'tell_joke',
                'description' => 'Fetch a family-friendly joke to tell. Use when the child asks for a joke or seems like they need cheering up.',
                'timeoutSeconds' => 8,
                'parameters' => [],
            ],
        ];
    }
}
