<?php
namespace App\Services;

use App\Core\Config;

class AiContentService
{
    public function polishContent(string $html): string
    {
        $key = Config::get('DEEPSEEK_API_KEY');
        if (!$key) return $html;

        $text = strip_tags($html);
        $result = $this->callDeepSeek([
            ['role' => 'system', 'content' => 'You are a professional editor. Polish the following text for grammar, clarity, and style. Return only the improved text, preserve the original language. Respond in the SAME LANGUAGE as the input text.'],
            ['role' => 'user', 'content' => $text],
        ], 0.3);

        return $result ?: $html;
    }

    public function generateArticle(string $topic, string $keywords): array
    {
        $key = Config::get('DEEPSEEK_API_KEY');
        if (!$key) return ['title' => $topic, 'excerpt' => '', 'content' => '', 'meta_description' => ''];

        $prompt = "Write a professional blog article about: $topic. Keywords to include: $keywords. Respond in Spanish. Return valid JSON with keys: title, excerpt, content (HTML with <p>, <h2>, <h3>), meta_description.";
        $result = $this->callDeepSeek([
            ['role' => 'system', 'content' => 'You are an expert SEO content writer. Write professional, engaging content. Always respond in Spanish. Return ONLY valid JSON.'],
            ['role' => 'user', 'content' => $prompt],
        ], 0.7, 2000);

        $data = json_decode($result, true);
        return is_array($data) ? $data : ['title' => $topic, 'excerpt' => '', 'content' => '', 'meta_description' => ''];
    }

    public function suggestTopics(string $niche): array
    {
        $key = Config::get('DEEPSEEK_API_KEY');
        if (!$key) return [];

        $prompt = "Suggest 10 blog topic ideas about: $niche. Respond in Spanish. Return valid JSON array of strings.";
        $result = $this->callDeepSeek([
            ['role' => 'system', 'content' => 'You are a content strategist. Suggest engaging blog topics. Respond in Spanish. Return ONLY valid JSON array.'],
            ['role' => 'user', 'content' => $prompt],
        ], 0.8);

        $data = json_decode($result, true);
        return is_array($data) ? $data : [];
    }

    private function callDeepSeek(array $messages, float $temperature = 0.7, int $maxTokens = 1500): ?string
    {
        $key = Config::get('DEEPSEEK_API_KEY');
        if (!$key) return null;

        $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $key,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'deepseek-chat',
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]),
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) return null;
        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }
}
