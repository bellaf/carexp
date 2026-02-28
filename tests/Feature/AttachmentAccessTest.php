<?php

use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('attachment owner can open a private attachment', function () {
    Storage::fake('local');

    $expense = Expense::factory()->create();
    Storage::disk('local')->put('attachments/test/receipt.pdf', 'pdf');

    $attachment = $expense->attachments()->create([
        'user_id' => $expense->user_id,
        'disk' => 'local',
        'path' => 'attachments/test/receipt.pdf',
        'original_name' => 'receipt.pdf',
        'mime_type' => 'application/pdf',
        'size' => 3,
    ]);

    $this->actingAs($expense->user)
        ->get(route('attachments.show', $attachment))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('other users cannot open private attachments', function () {
    Storage::fake('local');

    $expense = Expense::factory()->create();
    $otherUser = User::factory()->create();
    Storage::disk('local')->put('attachments/test/receipt.pdf', 'pdf');

    $attachment = $expense->attachments()->create([
        'user_id' => $expense->user_id,
        'disk' => 'local',
        'path' => 'attachments/test/receipt.pdf',
        'original_name' => 'receipt.pdf',
        'mime_type' => 'application/pdf',
        'size' => 3,
    ]);

    $this->actingAs($otherUser)
        ->get(route('attachments.show', $attachment))
        ->assertNotFound();
});
