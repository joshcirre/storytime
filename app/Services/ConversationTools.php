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
                'description' => 'Get the real, current weather for a city. Always call this whenever the user mentions the weather, the temperature, being hot or cold, a city, or where they live — never guess or make up weather. If you do not know which city they mean, ask them first, then call this tool.',
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
                'description' => 'Fetch a knock-knock joke to perform. Always call this when the user asks for a joke — never invent your own. Returns a setup and a punchline to perform as a back-and-forth knock-knock joke.',
                'timeoutSeconds' => 8,
                'parameters' => [],
            ],
        ];
    }
}
