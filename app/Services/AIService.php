<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AIService
{
    public function generateTasks(string $goalTitle, ?string $endDate)
    {
        $prompt = "
        Objetivo: {$goalTitle}
        Fecha límite: {$endDate}
        Genera una lista de micro tareas accionables y medibles.
        Devuélvelo como lista simple.
        ";

        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->withoutVerifying() // <--- AGREGA ESTO
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ]);

        return $response->json()['choices'][0]['message']['content'] ?? '';
    }
}