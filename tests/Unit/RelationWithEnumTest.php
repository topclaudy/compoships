<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Tests\Enums\UserProfileType;
use Awobaz\Compoships\Tests\Models\User;
use Awobaz\Compoships\Tests\Models\UserProfile;
use Awobaz\Compoships\Tests\Models\UserProfileText;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Eloquent\Model;

class RelationWithEnumTest extends TestCase
{
    /**
     * @var User
     */
    private $user;

    public function setUp(): void
    {
        if (getPHPVersion() < 8.1) {
            $this->markTestSkipped('This test requires PHP 8.1 or higher');
        }

        Model::unguard();

        $user = new User();
        $user->save();

        $user->userProfiles()->createMany([
            ['user_profile_type' => UserProfileType::Email],
            ['user_profile_type' => UserProfileType::Url],
        ]);

        $user->userProfiles->each(function ($userProfile) {
            $userProfile->userProfileTexts()->createMany([
                ['user_profile_text' => 'text_1'],
                ['user_profile_text' => 'text_2'],
            ]);
        });

        $this->user = User::first();
    }

    /**
     * @covers \Awobaz\Compoships\Database\Eloquent\Relations\HasOneOrMany
     */
    public function test_lazy_load_has_many_relation_with_enum()
    {
        $this->assertNotEmpty($this->user->userProfiles);

        $this->user->userProfiles->each(function (UserProfile $userProfile) {
            $this->assertInstanceOf(UserProfileType::class, $userProfile->user_profile_type);
        });
    }

    /**
     * @covers \Awobaz\Compoships\Database\Eloquent\Relations\HasOneOrMany
     */
    public function test_eager_load_has_many_relation_with_enum()
    {
        $this->user->load('userProfiles');

        $this->assertNotEmpty($this->user->userProfiles);

        $this->user->userProfiles->each(function (UserProfile $userProfile) {
            $this->assertInstanceOf(UserProfileType::class, $userProfile->user_profile_type);
        });
    }

    /**
     * @covers \Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo
     */
    public function test_lazy_load_belongs_to_relation_with_enum()
    {
        $userProfiles = UserProfile::all();

        $this->assertNotEmpty($userProfiles);

        $userProfiles->each(function (UserProfile $userProfile) {
            $this->assertInstanceOf(User::class, $userProfile->user);
        });
    }

    /**
     * @covers \Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo
     */
    public function test_eager_load_belongs_to_relation_with_enum()
    {
        $userProfiles = UserProfile::with('user')->get();

        $this->assertNotEmpty($userProfiles);

        $userProfiles->each(function (UserProfile $userProfile) {
            $this->assertInstanceOf(User::class, $userProfile->user);
        });
    }

    /**
     * @covers \Awobaz\Compoships\Database\Eloquent\Relations\HasOneOrMany
     */
    public function test_lazy_load_has_many_relation_with_enum_and_composite_key()
    {
        $this->assertNotEmpty($this->user->userProfiles);

        $this->user->userProfiles->each(function (UserProfile $userProfile) {
            $this->assertNotEmpty($userProfile->userProfileTexts);

            $userProfile->userProfileTexts->each(function (UserProfileText $userProfileText) {
                $this->assertInstanceOf(UserProfileType::class, $userProfileText->user_profile_type);
            });
        });
    }

    /**
     * @covers \Awobaz\Compoships\Database\Eloquent\Relations\HasOneOrMany
     */
    public function test_eager_load_has_many_relation_with_enum_and_composite_key()
    {
        $this->user->load('userProfiles.userProfileTexts');

        $this->assertNotEmpty($this->user->userProfiles);

        $this->user->userProfiles->each(function (UserProfile $userProfile) {
            $this->assertNotEmpty($userProfile->userProfileTexts);

            $userProfile->userProfileTexts->each(function (UserProfileText $userProfileText) {
                $this->assertInstanceOf(UserProfileType::class, $userProfileText->user_profile_type);
            });
        });
    }

    /**
     * @covers \Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo
     */
    public function test_lazy_load_belongs_to_relation_with_enum_and_composite_key()
    {
        $userProfileTexts = UserProfileText::all();

        $this->assertNotEmpty($userProfileTexts);

        $userProfileTexts->each(function (UserProfileText $userProfileText) {
            $this->assertInstanceOf(UserProfile::class, $userProfileText->userProfile);
        });
    }

    /**
     * @covers \Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo
     */
    public function test_eager_load_belongs_to_relation_with_enum_and_composite_key()
    {
        $userProfileTexts = UserProfileText::with('userProfile')->get();

        $this->assertNotEmpty($userProfileTexts);

        $userProfileTexts->each(function (UserProfileText $userProfileText) {
            $this->assertInstanceOf(UserProfile::class, $userProfileText->userProfile);
        });
    }
}
