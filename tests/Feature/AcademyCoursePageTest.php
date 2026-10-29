<?php

namespace Tests\Feature;

use App\Models\AcademyCourse;
use App\Models\AcademyReview;
use App\Models\User;
use App\Support\Academy\AcademyAccess;
use App\Support\Academy\AcademyReviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class AcademyCoursePageTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function course(array $attributes = []): AcademyCourse
    {
        $course = AcademyCourse::query()->create($attributes + [
            'title' => 'Charte Tunivisions', 'slug' => 'charte-tunivisions', 'level' => 'beginner',
            'status' => 'published', 'summary' => 'Les valeurs de la Fondation.', 'description' => 'Présentation détaillée.',
            'objectives' => "- Comprendre la charte\nConnaître les valeurs\n\n",
            'prerequisites' => 'Aucun', 'audience' => 'Tous les membres',
            'instructor_name' => 'Amel Ben Salah', 'instructor_title' => 'Directrice de la formation', 'instructor_bio' => 'Vingt ans d’expérience.',
        ]);
        $course->modules()->create(['title' => 'Introduction', 'sort_order' => 1])
            ->lessons()->create(['title' => 'Bienvenue', 'kind' => 'text', 'points' => 5, 'duration_minutes' => 90, 'sort_order' => 1]);

        return $course;
    }

    public function test_course_page_shows_hero_objectives_trainer_and_information(): void
    {
        $user = User::factory()->create();
        $course = $this->course();

        $this->actingAs($user)->get('/academy/cours/charte-tunivisions')->assertOk()
            ->assertSee('Charte Tunivisions')->assertSee('Les valeurs de la Fondation.')
            ->assertSee('À propos de cette formation')->assertSee('Présentation détaillée.')
            ->assertSee('Objectifs de la formation')->assertSee('Comprendre la charte')->assertSee('Connaître les valeurs')
            ->assertSee('Prérequis')->assertSee('À qui s’adresse cette formation')
            ->assertSee('Votre formateur')->assertSee('Amel Ben Salah')->assertSee('Directrice de la formation')->assertSee('Vingt ans d’expérience.')
            ->assertSee('Informations')->assertSee('1 h 30')->assertSee('Avis des apprenants')->assertSee('Aucun avis pour le moment.');
    }

    public function test_optional_sections_are_hidden_when_empty(): void
    {
        $user = User::factory()->create();
        $this->course(['objectives' => null, 'prerequisites' => null, 'audience' => null, 'instructor_name' => null, 'description' => null]);

        $this->actingAs($user)->get('/academy/cours/charte-tunivisions')->assertOk()
            ->assertDontSee('Objectifs de la formation')->assertDontSee('Prérequis')->assertDontSee('Votre formateur')->assertDontSee('À propos de cette formation');
    }

    public function test_only_enrolled_members_review_once_and_can_edit(): void
    {
        $user = User::factory()->create();
        $course = $this->course();
        $url = '/academy/cours/charte-tunivisions/avis';

        $this->actingAs($user)->post($url, ['course_rating' => 5])->assertRedirect('/academy/cours/charte-tunivisions');
        $this->assertSame(0, AcademyReview::query()->count());

        AcademyAccess::enroll($user, $course, 'free');
        $this->actingAs($user)->post($url, ['course_rating' => 0])->assertSessionHasErrors('course_rating');
        $this->actingAs($user)->post($url, ['course_rating' => 6])->assertSessionHasErrors('course_rating');
        $this->actingAs($user)->post($url, ['course_rating' => 4, 'course_comment' => 'Très clair', 'instructor_rating' => 5, 'instructor_comment' => 'Excellente pédagogie'])->assertRedirect();
        $this->actingAs($user)->post($url, ['course_rating' => 5, 'course_comment' => 'Très clair !'])->assertRedirect();

        $this->assertSame(1, AcademyReview::query()->count());
        $review = AcademyReview::query()->firstOrFail();
        $this->assertSame(5, $review->course_rating);
        $this->assertNull($review->instructor_rating);
    }

    public function test_reviews_are_listed_summarised_and_hidden_ones_excluded(): void
    {
        $course = $this->course();
        $first = User::factory()->create(['first_name' => 'Sami', 'last_name' => 'Trabelsi', 'name' => 'Sami Trabelsi']);
        $second = User::factory()->create();
        $third = User::factory()->create();

        AcademyReview::query()->create(['course_id' => $course->id, 'user_id' => $first->id, 'course_rating' => 5, 'course_comment' => 'Formation au top', 'instructor_rating' => 4, 'instructor_comment' => 'Formatrice à l’écoute']);
        AcademyReview::query()->create(['course_id' => $course->id, 'user_id' => $second->id, 'course_rating' => 3]);
        AcademyReview::query()->create(['course_id' => $course->id, 'user_id' => $third->id, 'course_rating' => 1, 'course_comment' => 'Avis masqué', 'is_hidden' => true]);

        $summary = AcademyReviews::summary($course);
        $this->assertSame(2, $summary['count']);
        $this->assertSame(4.0, $summary['course_avg']);
        $this->assertSame(1, $summary['instructor_count']);
        $this->assertSame(4.0, $summary['instructor_avg']);
        $this->assertSame(1, $summary['distribution'][5]);

        $this->assertSame('Sami T.', AcademyReviews::displayName($first));

        $this->actingAs($third)->get('/academy/cours/charte-tunivisions')->assertOk()
            ->assertSee('Formation au top')->assertSee('Formatrice à l’écoute')->assertSee('Sami T.')->assertDontSee('Sami Trabelsi')->assertDontSee('Avis masqué');
    }

    public function test_admin_edits_presentation_fields_and_moderates_reviews(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $course = $this->course();
        $member = User::factory()->create();
        $review = AcademyReview::query()->create(['course_id' => $course->id, 'user_id' => $member->id, 'course_rating' => 2, 'course_comment' => 'Bof']);

        $this->actingAs($admin)->put(route('admin.academy.courses.update', $course), [
            'title' => 'Charte Tunivisions', 'level' => 'beginner', 'status' => 'published', 'language' => 'Arabe',
            'objectives' => "Objectif A\nObjectif B", 'instructor_name' => 'Nouveau formateur', 'instructor_title' => 'Expert',
            'instructor_bio' => 'Bio', 'instructor_photo' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertRedirect();

        $course->refresh();
        $this->assertSame('Arabe', $course->language);
        $this->assertSame(['Objectif A', 'Objectif B'], AcademyCourse::lines($course->objectives));
        $this->assertNotNull($course->instructor_photo_path);
        Storage::disk('public')->assertExists($course->instructor_photo_path);

        $this->actingAs($admin)->get(route('admin.academy.courses.edit', $course))->assertOk()->assertSee('Avis des membres')->assertSee('Bof');

        $this->actingAs($admin)->patch(route('admin.academy.courses.reviews.toggle', $review))->assertRedirect();
        $this->assertTrue($review->fresh()->is_hidden);

        $this->actingAs($admin)->put(route('admin.academy.courses.update', $course), [
            'title' => 'Charte Tunivisions', 'level' => 'beginner', 'status' => 'published', 'remove_instructor_photo' => '1',
        ])->assertRedirect();
        $this->assertNull($course->fresh()->instructor_photo_path);

        $this->actingAs($admin)->delete(route('admin.academy.courses.reviews.destroy', $review))->assertRedirect();
        $this->assertSame(0, AcademyReview::query()->count());
    }

    public function test_members_cannot_moderate_reviews(): void
    {
        $course = $this->course();
        $member = User::factory()->create();
        $review = AcademyReview::query()->create(['course_id' => $course->id, 'user_id' => $member->id, 'course_rating' => 2]);

        $this->actingAs($member)->patch(route('admin.academy.courses.reviews.toggle', $review))->assertForbidden();
        $this->actingAs($member)->delete(route('admin.academy.courses.reviews.destroy', $review))->assertForbidden();
    }
}
