<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});

Broadcast::channel(
    'exam.{examId}.student.{studentId}',
    function (User $user, int $_examId, int $studentId): bool {
        return $user->id === $studentId;
    },
);
