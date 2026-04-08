<?php
// WA Cerdas - Homepage
header("Content-Type: application/json");

$response = [
    "service"     => "wa-cerdas",
    "status"      => "running",
    "description" => "WhatsApp webhook handler with DeepSeek AI integration",
    "endpoints"   => [
        [
            "method"      => "POST",
            "path"        => "/api/webhook",
            "description" => "Receive incoming WhatsApp messages and return an AI-generated reply",
            "headers"     => [
                "Content-Type"   => "application/json",
                "X-Webhook-Token" => "Your secret token (required when WEBHOOK_SECRET env var is set)"
            ],
            "body" => [
                "app"     => "Name of the chat application",
                "sender"  => "Sender identifier",
                "message" => "The user message to process"
            ],
            "response" => [
                "status"    => "success",
                "reply"     => "AI-generated response",
                "timestamp" => "Unix timestamp"
            ]
        ]
    ],
    "environment" => [
        "DEEPSEEK_API_KEY" => getenv('DEEPSEEK_API_KEY') ? "configured" : "not set",
        "WEBHOOK_SECRET"   => getenv('WEBHOOK_SECRET')   ? "configured" : "not set (open access)"
    ]
];

http_response_code(200);
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
