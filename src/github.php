<?php

function getBranches($config)
{
    $url = "https://api.github.com/repos/{$config['github']['repo']}/branches";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$config['github']['token']}",
            "User-Agent: Deploy-App"
        ]
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }

    curl_close($ch);

    return json_decode($response, true);
}

function getLatestCommit($config, $branch)
{
    $url = "https://api.github.com/repos/{$config['github']['repo']}/commits/{$branch}";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$config['github']['token']}",
            "User-Agent: Deploy-App"
        ]
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return null;
    }

    curl_close($ch);

    return json_decode($response, true);
}