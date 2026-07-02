<?php

namespace App\Http\Controllers\Relay;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RelayToolController extends Controller
{
    /**
     * Current weather for a city via Open-Meteo (geocoding + forecast).
     * Returns a payload the avatar can speak from; lookup failures are
     * returned as a speakable error rather than an HTTP error.
     */
    public function weather(Request $request): JsonResponse
    {
        $validated = $request->validate(['city' => ['required', 'string', 'max:100']]);

        $place = Http::timeout(5)
            ->get('https://geocoding-api.open-meteo.com/v1/search', [
                'name' => $validated['city'],
                'count' => 1,
            ])
            ->throw()
            ->json('results.0');

        if ($place === null) {
            return response()->json(['error' => "I couldn't find a city called {$validated['city']}."]);
        }

        $current = Http::timeout(5)
            ->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $place['latitude'],
                'longitude' => $place['longitude'],
                'current' => 'temperature_2m,apparent_temperature,weather_code,wind_speed_10m',
                'temperature_unit' => 'fahrenheit',
                'wind_speed_unit' => 'mph',
            ])
            ->throw()
            ->json('current');

        return response()->json([
            'city' => $place['name'],
            'region' => $place['admin1'] ?? null,
            'country' => $place['country'] ?? null,
            'conditions' => $this->describeWeatherCode($current['weather_code'] ?? -1),
            'temperatureF' => round($current['temperature_2m']),
            'feelsLikeF' => round($current['apparent_temperature']),
            'windMph' => round($current['wind_speed_10m']),
        ]);
    }

    /**
     * A family-friendly joke via icanhazdadjoke.
     */
    public function joke(): JsonResponse
    {
        $joke = Http::timeout(5)
            ->acceptJson()
            ->withHeader('User-Agent', config('app.name').' (character demo)')
            ->get('https://icanhazdadjoke.com/')
            ->throw()
            ->json('joke');

        return response()->json(['joke' => $joke]);
    }

    /**
     * Translate a WMO weather code into a kid-friendly description.
     */
    protected function describeWeatherCode(int $code): string
    {
        return match (true) {
            $code === 0 => 'clear and sunny',
            $code <= 2 => 'partly cloudy',
            $code === 3 => 'cloudy',
            $code <= 48 => 'foggy',
            $code <= 57 => 'drizzly',
            $code <= 67 => 'rainy',
            $code <= 77 => 'snowy',
            $code <= 82 => 'rainy with showers',
            $code <= 86 => 'snowy with flurries',
            $code <= 99 => 'stormy with thunder',
            default => 'mysterious',
        };
    }
}
