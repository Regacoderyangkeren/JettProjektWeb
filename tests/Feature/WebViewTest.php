<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebViewTest extends TestCase
{
    public function test_dashboard_view_renders_with_app_data(): void
    {
        $html = view('dashboard', [
            'profile' => ['firstName' => 'Jett', 'lastName' => 'User', 'email' => 'jett@example.test'],
            'projects' => [$this->project()],
            'tasks' => [$this->task()],
            'inboxItems' => [$this->inboxItem()],
            'notes' => [$this->note()],
        ])->render();

        $this->assertStringContainsString('Dashboard', $html);
        $this->assertStringContainsString('Recent projects', $html);
    }

    public function test_project_views_render(): void
    {
        $indexHtml = view('projects.index', [
            'projects' => [$this->project()],
        ])->render();

        $showHtml = view('projects.show', [
            'project' => $this->project(),
            'tasks' => [$this->task()],
        ])->render();

        $this->assertStringContainsString('Project list', $indexHtml);
        $this->assertStringContainsString('Create task', $showHtml);
    }

    public function test_notes_and_inbox_views_render(): void
    {
        $notesHtml = view('notes.index', [
            'notes' => [$this->note()],
        ])->render();

        $inboxHtml = view('inbox.index', [
            'items' => [$this->inboxItem()],
            'connectionItems' => [],
        ])->render();

        $this->assertStringContainsString('Saved notes', $notesHtml);
        $this->assertStringContainsString('Team and task inbox', $inboxHtml);
    }

    public function test_connections_view_renders(): void
    {
        $html = view('connections.index', [
            'currentUser' => [
                'id' => 'user_1',
                'connectionIds' => ['user_2'],
                'pendingConnectionIds' => ['user_3'],
                'sentConnectionRequestIds' => ['user_4'],
            ],
            'users' => [
                $this->user('user_2', 'Connected', 'ONLINE'),
                $this->user('user_3', 'Pending', 'STANDBY'),
                $this->user('user_4', 'Sent', 'OFFLINE'),
                $this->user('user_5', 'Discover', 'OFFLINE'),
            ],
        ])->render();

        $this->assertStringContainsString('Connections', $html);
        $this->assertStringContainsString('Pending requests', $html);
    }

    private function project(): array
    {
        return [
            'id' => 'project_1',
            'name' => 'Sample project',
            'description' => 'A test project',
            'status' => 'ACTIVE',
            'memberIds' => ['user_1'],
        ];
    }

    private function task(): array
    {
        return [
            'id' => 'task_1',
            'title' => 'Sample task',
            'description' => 'A test task',
            'type' => 'TODO_LIST',
            'status' => 'TODO',
            'priority' => 'MEDIUM',
            'reviewState' => '',
        ];
    }

    private function inboxItem(): array
    {
        return [
            'id' => 'inbox_1',
            'title' => 'Sample inbox',
            'body' => 'A test inbox item',
            'type' => 'task_review',
            'read' => false,
        ];
    }

    private function note(): array
    {
        return [
            'id' => 'note_1',
            'name' => 'Sample note',
            'description' => 'A test note',
            'content' => 'Note body',
            'date' => '2026-05-26',
            'time' => '10:00',
        ];
    }

    private function user(string $id, string $firstName, string $status): array
    {
        return [
            'id' => $id,
            'firstName' => $firstName,
            'lastName' => 'User',
            'alias' => strtolower($firstName),
            'email' => strtolower($firstName).'@example.test',
            'profilePictureUrl' => '',
            'status' => $status,
        ];
    }
}
