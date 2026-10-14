<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Rfp\Rfp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commentable_type' => (new Rfp)->getMorphClass(),
            'commentable_id' => Rfp::factory(),
            'user_id' => User::factory(),
            'body' => '<p>'.fake()->sentence().'</p>',
        ];
    }
}
