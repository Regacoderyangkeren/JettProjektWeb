<?php

return [
    'firebase' => [
        'api_key' => env('FIREBASE_API_KEY', env('VITE_FIREBASE_API_KEY')),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'project_number' => env('FIREBASE_PROJECT_NUMBER'),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
        'firestore_database' => env('FIRESTORE_DATABASE', env('FIRESTORE_DATABASE_ID', '(default)')),
        'region' => env('FIRESTORE_REGION', 'asia-southeast2'),
        'service_account_json' => env('FIREBASE_SERVICE_ACCOUNT_JSON'),
    ],

    'appwrite' => [
        'endpoint' => env('APPWRITE_ENDPOINT'),
        'project_id' => env('APPWRITE_PROJECT_ID'),
        'bucket_id' => env('APPWRITE_BUCKET_ID'),
    ],

    'collections' => [
        'users' => env('COLLECTION_USERS', 'users'),
        'projects' => env('COLLECTION_PROJECTS', 'projects'),
        'tasks' => env('COLLECTION_TASKS', 'tasks'),
        'teams' => env('COLLECTION_TEAMS', 'teams'),
        'notes' => env('COLLECTION_NOTES', 'notes'),
        'team_invites' => env('COLLECTION_TEAM_INVITES', 'teamInvites'),
        'notifications' => env('COLLECTION_NOTIFICATIONS', 'notifications'),
        'connection_chats' => env('COLLECTION_CONNECTION_CHATS', 'connectionChats'),
        'developer_chats' => env('COLLECTION_DEVELOPER_CHATS', 'developerChats'),
    ],
];
