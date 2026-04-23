<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BaiDang;
use App\Services\AiMatchingService;

// Simulate auth
\Illuminate\Support\Facades\Auth::shouldReceive('id')->andReturn(3); // Assuming user 3 owns post 1014

$postId = 1014;
$post = BaiDang::find($postId);

echo "=== TESTING MATCHES FOR POST 1014 ===\n";
echo "Post: {$post->tieu_de}\n";

// Get user
$user = \App\Models\User::find(3);
echo "User: {$user?->ho_ten}\n";
echo "User address: " . ($user?->dia_chi ?? 'NOT SET') . "\n";

// Call matches
$aiService = app(AiMatchingService::class);

try {
    $response = \Illuminate\Support\Facades\Http::timeout(10)
        ->post(env('AI_SERVICE_URL') . '/matches', [
            'post_id' => $postId,
            'posts' => [], // Will be populated by controller
            'user_has_address' => !empty($user?->dia_chi),
            'user_interests' => [],
        ]);
    
    echo "\nAI Service Response:\n";
    if ($response->ok()) {
        $data = $response->json();
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "Error: " . $response->status() . "\n";
        echo $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
