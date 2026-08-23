<?php

use App\Enums\UserRole;
use App\Models\ActionRequest;
use App\Models\User;

it('allows a manager to submit an action request', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager)
        ->post(route('action-requests.store'), [
            'type' => 'price_change',
            'reason' => 'The supplier price changed and the menu price needs review.',
        ])
        ->assertRedirect();

    expect(ActionRequest::first())
        ->requested_by->toBe($manager->id)
        ->type->toBe('price_change')
        ->status->toBe('pending');
});

it('prevents a cashier from submitting an action request', function () {
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);

    $this->actingAs($cashier)
        ->post(route('action-requests.store'), [
            'type' => 'other',
            'reason' => 'This should not be accepted.',
        ])
        ->assertForbidden();
});

it('allows only an admin to review a pending request', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $actionRequest = ActionRequest::create([
        'requested_by' => $manager->id,
        'type' => 'inventory_adjustment',
        'reason' => 'Correct the physical count.',
    ]);

    $this->actingAs($admin)
        ->patch(route('action-requests.review', $actionRequest), [
            'status' => 'approved',
            'review_note' => 'Approved after checking the stock sheet.',
        ])
        ->assertRedirect();

    expect($actionRequest->fresh())
        ->status->toBe('approved')
        ->reviewed_by->toBe($admin->id)
        ->review_note->toBe('Approved after checking the stock sheet.');
});
