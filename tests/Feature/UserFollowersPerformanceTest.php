<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserFollowers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserFollowersPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_friends_avoids_n_plus_one_queries()
    {
        $user = new User();
        $user->fullname = 'Main User';
        $user->username = 'mainuser';
        $user->email = 'main@example.com';
        $user->password = bcrypt('secret');
        $user->status = '1';
        $user->save();

        $friendCount = 10;
        for ($i = 1; $i <= $friendCount; $i++) {
            $friend = new User();
            $friend->fullname = "Friend {$i}";
            $friend->username = "friend_{$i}";
            $friend->email = "friend{$i}@example.com";
            $friend->password = bcrypt('secret');
            $friend->status = '1';
            $friend->save();

            // Alternate relationship directions to test bidirectional support
            if ($i % 2 === 0) {
                UserFollowers::create([
                    'user_id' => $user->id,
                    'follower_user_id' => $friend->id,
                    'status' => '1',
                ]);
            } else {
                UserFollowers::create([
                    'user_id' => $friend->id,
                    'follower_user_id' => $user->id,
                    'status' => '1',
                ]);
            }
        }

        // Add a deleted/inactive user relationship (status '3') which should be filtered out
        $inactiveFriend = new User();
        $inactiveFriend->fullname = 'Inactive Friend';
        $inactiveFriend->username = 'inactive_friend';
        $inactiveFriend->email = 'inactive@example.com';
        $inactiveFriend->password = bcrypt('secret');
        $inactiveFriend->status = '3';
        $inactiveFriend->save();

        UserFollowers::create([
            'user_id' => $user->id,
            'follower_user_id' => $inactiveFriend->id,
            'status' => '1',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/GetFriends');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);
        $response->assertJson(['status' => true]);

        $data = $response->json('data.friends');
        $this->assertCount($friendCount, $data);

        // Before optimization, this ran 1 query for followers + N queries for each friend (12 queries).
        // With optimization, exactly 2 queries are run regardless of friend count.
        $this->assertCount(2, $queries, 'GetFriends should execute exactly 2 queries instead of N+1');
    }

    public function test_get_all_friend_request_avoids_n_plus_one_queries()
    {
        $user = new User();
        $user->fullname = 'Receiver User';
        $user->username = 'receiver';
        $user->email = 'receiver@example.com';
        $user->password = bcrypt('secret');
        $user->status = '1';
        $user->save();

        $requesterCount = 8;
        for ($i = 1; $i <= $requesterCount; $i++) {
            $requester = new User();
            $requester->fullname = "Requester {$i}";
            $requester->username = "requester_{$i}";
            $requester->email = "requester{$i}@example.com";
            $requester->password = bcrypt('secret');
            $requester->status = '1';
            $requester->save();

            UserFollowers::create([
                'user_id' => $requester->id,
                'follower_user_id' => $user->id,
                'status' => '2',
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/GetAllFriendRequest');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);
        $response->assertJson(['status' => true]);

        $data = $response->json('data.requests');
        $this->assertCount($requesterCount, $data);

        // Before optimization, this ran 1 query for requests + N queries for each requester (9 queries).
        // With optimization, exactly 2 queries are run regardless of requester count.
        $this->assertCount(2, $queries, 'GetAllFriendRequest should execute exactly 2 queries instead of N+1');
    }
}
