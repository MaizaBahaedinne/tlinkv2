<?php

namespace Tests\Feature;

use App\Models\AppModule;
use App\Models\AppNotification;
use App\Models\Membership;
use App\Models\MentoringMentor;
use App\Models\MentoringTicket;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\Mentoring\Mentoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class MentoringTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function mentor(bool $manager = false): User
    {
        $user = User::factory()->create();
        MentoringMentor::query()->create(['user_id' => $user->id, 'is_manager' => $manager, 'is_active' => true]);

        return $user->fresh();
    }

    private function ticket(User $requester, array $overrides = []): MentoringTicket
    {
        return Mentoring::submit($requester->fresh(), array_merge([
            'type' => 'incident',
            'audience' => 'personal',
            'title' => 'Conflit dans mon équipe',
            'description' => 'Deux membres de mon équipe ne se parlent plus depuis le dernier événement.',
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'audience' => 'personal',
            'type' => 'request',
            'title' => 'Besoin d’un accompagnement',
            'description' => 'Je prépare mon premier événement et je souhaite être guidé sur l’organisation.',
        ], $overrides);
    }

    public function test_guests_are_redirected_and_the_module_can_be_switched_off(): void
    {
        $this->get('/mentorat')->assertRedirect('/connexion');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/mentorat')->assertOk()->assertSee('Mentorat')->assertSee('Nouvelle demande');

        AppModule::query()->where('key', 'mentoring.tickets')->update(['is_active' => false]);
        $this->actingAs($user)->get('/mentorat')->assertNotFound();
        $this->actingAs($user)->post('/mentorat', $this->payload())->assertNotFound();
    }

    public function test_a_member_reports_a_personal_ticket_and_managers_are_notified(): void
    {
        $manager = $this->mentor(true);
        $member = User::factory()->create();

        $this->actingAs($member)->post('/mentorat', $this->payload())->assertRedirect();

        $ticket = MentoringTicket::query()->firstOrFail();
        $this->assertSame('MT-00001', $ticket->reference);
        $this->assertSame('new', $ticket->status);
        $this->assertSame($member->id, (int) $ticket->requester_id);
        $this->assertNull($ticket->node_id);

        $this->assertSame(1, AppNotification::query()->where('user_id', $manager->id)->where('type', 'mentoring.ticket')->count());
        $this->assertSame(0, AppNotification::query()->where('user_id', $member->id)->count());

        $this->actingAs($member)->get('/mentorat')->assertOk()->assertSee('MT-00001')->assertSee('Reçue');
        $this->actingAs($member)->get(route('mentoring.show', $ticket))->assertOk()->assertSee('Besoin d’un accompagnement')->assertDontSee('Note interne');
    }

    public function test_ticket_validation(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->post('/mentorat', $this->payload(['title' => '', 'description' => 'trop court']))->assertSessionHasErrors(['title', 'description']);
        $this->actingAs($member)->post('/mentorat', $this->payload(['type' => 'inconnu']))->assertSessionHasErrors('type');
        $this->actingAs($member)->post('/mentorat', $this->payload(['audience' => 'collective']))->assertSessionHasErrors('collective_name');

        $this->assertSame(0, MentoringTicket::query()->count());
    }

    public function test_a_collective_ticket_targets_a_club_of_the_member_or_a_free_collective(): void
    {
        $member = User::factory()->create();
        $club = OrgNode::query()->create(['kind' => 'club', 'title' => 'Club Robotique Sfax', 'status' => 'active', 'category' => 'university_club', 'scope' => 'university']);
        $foreign = OrgNode::query()->create(['kind' => 'club', 'title' => 'Club Étranger', 'status' => 'active', 'category' => 'university_club', 'scope' => 'university']);
        Membership::query()->create(['user_id' => $member->id, 'scope' => 'university', 'node_id' => $club->id, 'status' => 'approved']);

        $this->actingAs($member)->get('/mentorat/nouvelle')->assertOk()->assertSee('Club Robotique Sfax')->assertDontSee('Club Étranger');

        $this->actingAs($member)->post('/mentorat', $this->payload(['audience' => 'collective', 'node_id' => $foreign->id]))->assertSessionHasErrors('node_id');

        $this->actingAs($member)->post('/mentorat', $this->payload(['audience' => 'collective', 'node_id' => $club->id]))->assertRedirect();
        $ticket = MentoringTicket::query()->firstOrFail();
        $this->assertSame($club->id, (int) $ticket->node_id);
        $this->assertSame('club', $ticket->collective_type);
        $this->assertStringContainsString('Club Robotique Sfax', (string) $ticket->collectiveLabel());

        $this->actingAs($member)->post('/mentorat', $this->payload(['audience' => 'collective', 'collective_type' => 'project', 'collective_name' => 'Projet Eau Propre']))->assertRedirect();
        $project = MentoringTicket::query()->latest('id')->firstOrFail();
        $this->assertNull($project->node_id);
        $this->assertSame('Projet · Projet Eau Propre', $project->collectiveLabel());
    }

    public function test_only_the_requester_the_assigned_mentor_and_managers_can_open_a_ticket(): void
    {
        $manager = $this->mentor(true);
        $assigned = $this->mentor();
        $other = $this->mentor();
        $requester = User::factory()->create();
        $stranger = User::factory()->create();
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        $ticket = $this->ticket($requester);
        Mentoring::qualify($ticket, $manager, 'relations', 'high', null);
        Mentoring::assign($ticket->fresh(), $manager, $assigned->id);

        $url = route('mentoring.show', $ticket);
        $this->actingAs($requester)->get($url)->assertOk();
        $this->actingAs($assigned)->get($url)->assertOk();
        $this->actingAs($manager)->get($url)->assertRedirect(route('admin.mentoring.tickets.show', $ticket));
        $this->actingAs($manager)->get(route('admin.mentoring.tickets.show', $ticket))->assertOk();
        $this->actingAs($stranger)->get($url)->assertForbidden();
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->actingAs($admin)->get($url)->assertForbidden();
        $this->actingAs($admin)->get(route('admin.mentoring.tickets.show', $ticket))->assertForbidden();
        $this->actingAs($assigned)->get(route('admin.mentoring.tickets.show', $ticket))->assertForbidden();

        $this->actingAs($stranger)->post(route('mentoring.comment', $ticket), ['body' => 'Coucou'])->assertForbidden();
        $this->actingAs($other)->get('/admin/mentorat')->assertForbidden();
        $this->actingAs($requester)->get('/mentorat/accompagnements')->assertForbidden();
    }

    public function test_the_full_workflow_from_qualification_to_closure(): void
    {
        $manager = $this->mentor(true);
        $mentor = $this->mentor();
        $requester = User::factory()->create();
        $ticket = $this->ticket($requester);

        // Qualification : seul le manager mentor.
        $this->actingAs($requester)->post(route('mentoring.qualify', $ticket), ['category' => 'relations', 'priority' => 'high'])->assertForbidden();
        $this->actingAs($mentor)->post(route('mentoring.qualify', $ticket), ['category' => 'relations', 'priority' => 'high'])->assertForbidden();
        $this->actingAs($manager)->post(route('mentoring.qualify', $ticket), ['category' => 'relations', 'priority' => 'high', 'qualification_note' => 'Tension ancienne, à traiter vite.'])->assertRedirect();

        $ticket->refresh();
        $this->assertSame('qualified', $ticket->status);
        $this->assertSame('relations', $ticket->category);
        $this->assertSame('high', $ticket->priority);
        $this->assertSame(1, AppNotification::query()->where('user_id', $requester->id)->where('title', 'like', '%qualifiée%')->count());

        // Affectation.
        $this->actingAs($manager)->post(route('mentoring.assign', $ticket), ['mentor_id' => $mentor->id])->assertRedirect();
        $ticket->refresh();
        $this->assertSame('assigned', $ticket->status);
        $this->assertSame($mentor->id, (int) $ticket->mentor_id);
        $this->assertSame(1, AppNotification::query()->where('user_id', $mentor->id)->where('title', 'Nouvel accompagnement à suivre')->count());

        // Démarrage puis échanges : la note interne reste cachée du demandeur.
        $this->actingAs($requester)->post(route('mentoring.start', $ticket))->assertForbidden();
        $this->actingAs($mentor)->post(route('mentoring.start', $ticket))->assertRedirect();
        $this->assertSame('in_progress', $ticket->fresh()->status);

        $this->actingAs($mentor)->post(route('mentoring.comment', $ticket), ['body' => 'Bonjour, voici mon plan en trois étapes.'])->assertRedirect();
        $this->actingAs($mentor)->post(route('mentoring.comment', $ticket), ['body' => 'Piste : médiation par le président.', 'internal' => '1'])->assertRedirect();
        $this->actingAs($requester)->post(route('mentoring.comment', $ticket), ['body' => 'Merci, je commence demain.'])->assertRedirect();
        $this->actingAs($requester)->post(route('mentoring.comment', $ticket), ['body' => 'Note sauvage', 'internal' => '1'])->assertSessionHasErrors('ticket');

        $this->actingAs($requester)->get(route('mentoring.show', $ticket))->assertOk()
            ->assertSee('Bonjour, voici mon plan')->assertDontSee('médiation par le président')->assertDontSee('Tension ancienne')->assertDontSee('Retour d’expérience');
        $this->actingAs($mentor)->get(route('mentoring.show', $ticket))->assertOk()
            ->assertSee('médiation par le président')->assertSee('Tension ancienne');

        // Pas de résolution sans REX.
        $this->actingAs($mentor)->post(route('mentoring.resolve', $ticket))->assertSessionHasErrors('ticket');
        $this->assertSame('in_progress', $ticket->fresh()->status);

        $this->actingAs($requester)->put(route('mentoring.rex', $ticket), [])->assertForbidden();
        $this->actingAs($mentor)->put(route('mentoring.rex', $ticket), ['context' => 'Conflit entre deux membres'])->assertSessionHasErrors(['actions', 'outcome', 'lessons']);
        $this->actingAs($mentor)->put(route('mentoring.rex', $ticket), [
            'context' => 'Conflit entre deux membres après un événement.',
            'actions' => 'Deux entretiens individuels puis une médiation.',
            'outcome' => 'Réconciliation, collaboration reprise.',
            'lessons' => 'Intervenir tôt et séparer les faits des ressentis.',
            'recommendations' => 'Prévoir un point d’équipe après chaque événement.',
        ])->assertRedirect();

        $this->assertSame(1, \App\Models\MentoringRex::query()->where('ticket_id', $ticket->id)->count());
        $this->actingAs($mentor)->get(route('mentoring.show', $ticket))->assertSee('Réconciliation, collaboration reprise.');
        $this->actingAs($requester)->get(route('mentoring.show', $ticket))->assertDontSee('Réconciliation, collaboration reprise.');

        $this->actingAs($mentor)->post(route('mentoring.resolve', $ticket))->assertRedirect();
        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertSame(1, AppNotification::query()->where('user_id', $requester->id)->where('title', 'Demande résolue')->count());
        $this->assertSame(1, AppNotification::query()->where('user_id', $manager->id)->where('title', 'Demande résolue')->count());

        // Clôture : manager uniquement, puis réouverture possible.
        $this->actingAs($mentor)->post(route('mentoring.close', $ticket))->assertForbidden();
        $this->actingAs($manager)->post(route('mentoring.close', $ticket))->assertRedirect();
        $this->assertSame('closed', $ticket->fresh()->status);

        $this->actingAs($requester)->post(route('mentoring.comment', $ticket), ['body' => 'Encore une chose'])->assertSessionHasErrors('ticket');

        $this->actingAs($manager)->post(route('mentoring.reopen', $ticket))->assertRedirect();
        $this->assertSame('in_progress', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->closed_at);
    }

    public function test_workflow_rules_are_enforced(): void
    {
        $manager = $this->mentor(true);
        $mentor = $this->mentor();
        $requester = $this->mentor();
        $plain = User::factory()->create();
        $ticket = $this->ticket($plain);

        // Pas d'affectation sans qualification.
        $this->actingAs($manager)->post(route('mentoring.assign', $ticket), ['mentor_id' => $mentor->id])->assertSessionHasErrors('ticket');
        $this->assertNull($ticket->fresh()->mentor_id);

        Mentoring::qualify($ticket, $manager, 'project', 'normal', null);

        // Seul un mentor actif peut être choisi, jamais le demandeur lui-même.
        $this->actingAs($manager)->post(route('mentoring.assign', $ticket), ['mentor_id' => $plain->id])->assertSessionHasErrors('ticket');
        $own = $this->ticket($requester);
        Mentoring::qualify($own, $manager, 'project', 'normal', null);
        $this->actingAs($manager)->post(route('mentoring.assign', $own), ['mentor_id' => $requester->id])->assertSessionHasErrors('ticket');

        // Pas de démarrage ni de clôture hors étape.
        $this->actingAs($manager)->post(route('mentoring.start', $ticket))->assertSessionHasErrors('ticket');
        $this->actingAs($manager)->post(route('mentoring.close', $ticket))->assertSessionHasErrors('ticket');

        // Un manager n'instruit pas sa propre demande.
        $managerTicket = $this->ticket($manager);
        $this->actingAs($manager)->post(route('mentoring.qualify', $managerTicket), ['category' => 'other', 'priority' => 'low'])->assertForbidden();
        $this->actingAs($manager)->get(route('mentoring.show', $managerTicket))->assertOk()->assertDontSee('Qualifier le ticket');

        // Un ticket affecté ne peut plus être écarté.
        Mentoring::assign($ticket->fresh(), $manager, $mentor->id);
        $this->actingAs($manager)->post(route('mentoring.reject', $ticket), ['reason' => 'Hors périmètre'])->assertSessionHasErrors('ticket');
    }

    public function test_a_ticket_can_be_rejected_with_a_reason_and_can_be_reassigned(): void
    {
        $manager = $this->mentor(true);
        $first = $this->mentor();
        $second = $this->mentor();
        $requester = User::factory()->create();

        $rejected = $this->ticket($requester);
        $this->actingAs($manager)->post(route('mentoring.reject', $rejected), [])->assertSessionHasErrors('reason');
        $this->actingAs($manager)->post(route('mentoring.reject', $rejected), ['reason' => 'Hors périmètre du mentorat'])->assertRedirect();
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->actingAs($requester)->get(route('mentoring.show', $rejected))->assertSee('Hors périmètre du mentorat');
        $this->assertSame(1, AppNotification::query()->where('user_id', $requester->id)->where('title', 'like', '%pas été retenue%')->count());

        $ticket = $this->ticket($requester);
        Mentoring::qualify($ticket, $manager, 'training', 'low', null);
        Mentoring::assign($ticket->fresh(), $manager, $first->id);
        Mentoring::assign($ticket->fresh(), $manager, $second->id);

        $this->assertSame($second->id, (int) $ticket->fresh()->mentor_id);
        $this->assertSame(1, AppNotification::query()->where('user_id', $first->id)->where('title', 'Accompagnement réaffecté')->count());
        $this->actingAs($first)->get(route('mentoring.show', $ticket))->assertForbidden();
    }

    public function test_manager_dashboard_lists_and_filters_tickets(): void
    {
        $manager = $this->mentor(true);
        $requester = User::factory()->create(['name' => 'Salma Ben Ali']);
        $urgent = $this->ticket($requester, ['title' => 'Budget bloqué']);
        Mentoring::qualify($urgent, $manager, 'resources', 'urgent', null);
        $this->ticket($requester, ['title' => 'Question sur les statuts', 'audience' => 'collective', 'collective_name' => 'Chapter Tunis']);

        $this->actingAs($manager)->get('/admin/mentorat')->assertOk()->assertSee('Budget bloqué')->assertSee('Question sur les statuts')->assertSee('Salma Ben Ali');
        $this->actingAs($manager)->get('/admin/mentorat?statut=qualified')->assertOk()->assertSee('Budget bloqué')->assertDontSee('Question sur les statuts');
        $this->actingAs($manager)->get('/admin/mentorat?public=collective')->assertOk()->assertSee('Question sur les statuts')->assertDontSee('Budget bloqué');
        $this->actingAs($manager)->get('/admin/mentorat?q=MT-00001')->assertOk()->assertSee('Budget bloqué')->assertDontSee('Question sur les statuts');
    }

    public function test_the_mentor_sees_only_the_tickets_assigned_to_them(): void
    {
        $manager = $this->mentor(true);
        $mentor = $this->mentor();
        $requester = User::factory()->create();

        $mine = $this->ticket($requester, ['title' => 'Mon accompagnement']);
        $other = $this->ticket($requester, ['title' => 'Pas pour moi']);
        Mentoring::qualify($mine, $manager, 'other', 'normal', null);
        Mentoring::assign($mine->fresh(), $manager, $mentor->id);

        $this->actingAs($mentor)->get('/mentorat/accompagnements')->assertOk()->assertSee('Mon accompagnement')->assertDontSee('Pas pour moi');
        $this->assertNotNull($other->id);
    }

    public function test_the_directory_of_mentors_is_managed_by_managers_and_platform_admins(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $member = User::factory()->create(['name' => 'Karim Mentor']);
        $plain = User::factory()->create();

        $this->actingAs($plain)->get('/admin/mentorat/mentors')->assertForbidden();
        $this->actingAs($plain)->post('/admin/mentorat/mentors', ['user_id' => $member->id])->assertForbidden();

        $this->actingAs($admin)->get('/admin/mentorat/mentors')->assertOk()->assertSee('Aucun manager mentor actif');
        $this->actingAs($admin)->get('/admin/mentorat/mentors?q=Karim')->assertOk()->assertSee('Karim Mentor');
        $this->actingAs($admin)->post('/admin/mentorat/mentors', ['user_id' => $member->id, 'manager' => '1', 'specialties' => 'Gouvernance'])->assertRedirect(route('admin.mentoring.mentors.index'));

        $row = MentoringMentor::query()->where('user_id', $member->id)->firstOrFail();
        $this->assertTrue($row->is_manager);
        $this->assertSame('Gouvernance', $row->specialties);
        $this->assertTrue(Mentoring::isManager($member->fresh()));

        // L'administrateur de la plateforme ne lit pas pour autant les tickets.
        $ticket = $this->ticket($plain);
        $this->actingAs($admin)->get(route('mentoring.show', $ticket))->assertForbidden();

        // Un manager peut à son tour gérer l'annuaire.
        $this->actingAs($member->fresh())->get('/admin/mentorat/mentors')->assertOk()->assertDontSee('Aucun manager mentor actif');

        $this->actingAs($admin)->patch(route('admin.mentoring.mentors.update', $row), ['specialties' => 'Projets', 'active' => '1'])->assertRedirect();
        $this->assertFalse($row->fresh()->is_manager);
        $this->assertSame('Projets', $row->fresh()->specialties);
    }

    public function test_a_mentor_with_open_tickets_cannot_be_removed_or_deactivated(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $manager = $this->mentor(true);
        $mentor = $this->mentor();
        $row = MentoringMentor::query()->where('user_id', $mentor->id)->firstOrFail();

        $ticket = $this->ticket(User::factory()->create());
        Mentoring::qualify($ticket, $manager, 'other', 'normal', null);
        Mentoring::assign($ticket->fresh(), $manager, $mentor->id);

        $this->actingAs($admin)->patch(route('admin.mentoring.mentors.update', $row), ['manager' => '0'])->assertSessionHasErrors('mentor');
        $this->actingAs($admin)->delete(route('admin.mentoring.mentors.destroy', $row))->assertSessionHasErrors('mentor');
        $this->assertTrue($row->fresh()->is_active);
        $this->assertNotNull(MentoringMentor::query()->find($row->id));

        // Une fois le ticket résolu, le retrait est possible.
        $ticket->fresh()->update(['status' => 'closed']);
        $this->actingAs($admin)->delete(route('admin.mentoring.mentors.destroy', $row))->assertRedirect();
        $this->assertNull(MentoringMentor::query()->find($row->id));
    }

    public function test_a_position_with_the_ticket_rights_in_its_matrix_acts_as_mentor_coordinator(): void
    {
        $coordinator = $this->holder('Coordinateur mentors', ['mentoring.tickets' => ['read', 'update']]);
        $observer = $this->holder('Observateur mentorat', ['mentoring.tickets' => ['read']]);
        $mentor = $this->mentor();
        $requester = User::factory()->create(['name' => 'Salma Ben Ali']);

        $this->actingAs($requester)->post('/mentorat', $this->payload(['title' => 'Besoin de soutien']))->assertRedirect();
        $ticket = MentoringTicket::query()->firstOrFail();

        // Le coordinateur est prévenu, voit la liste et instruit le ticket comme un manager mentor.
        $this->assertSame(1, AppNotification::query()->where('user_id', $coordinator->id)->where('type', 'mentoring.ticket')->count());
        $this->actingAs($coordinator)->get('/mentorat')->assertOk()->assertDontSee('Gestion des tickets');
        $this->actingAs($coordinator)->get('/admin/mentorat')->assertOk()->assertSee('Besoin de soutien');
        $this->actingAs($coordinator)->post(route('mentoring.qualify', $ticket), ['category' => 'other', 'priority' => 'normal'])->assertRedirect();
        $this->actingAs($coordinator)->post(route('mentoring.assign', $ticket), ['mentor_id' => $mentor->id])->assertRedirect();
        $this->assertSame('assigned', $ticket->fresh()->status);
        $this->actingAs($coordinator)->get('/admin/mentorat/mentors')->assertOk()->assertDontSee('Aucun manager mentor actif');

        // Le droit de consultation seule donne la liste et la fiche, sans aucune action.
        $this->assertSame(0, AppNotification::query()->where('user_id', $observer->id)->count());
        $this->actingAs($observer)->get('/admin/mentorat')->assertOk()->assertSee('Besoin de soutien');
        $this->actingAs($observer)->get(route('admin.mentoring.tickets.show', $ticket))->assertOk()->assertDontSee('Ajouter un message')->assertDontSee('Qualifier le ticket');
        $this->actingAs($observer)->post(route('mentoring.comment', $ticket), ['body' => 'Coucou'])->assertForbidden();
        $this->actingAs($observer)->post(route('mentoring.close', $ticket))->assertForbidden();
        $this->actingAs($observer)->get('/admin/mentorat/mentors')->assertOk()->assertDontSee('Ajouter un membre');
        $this->actingAs($observer)->post('/admin/mentorat/mentors', ['user_id' => $mentor->id])->assertForbidden();
    }

    public function test_ticket_management_lives_in_the_administration(): void
    {
        $manager = $this->mentor(true);
        $mentor = $this->mentor();
        $requester = User::factory()->create();
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $ticket = $this->ticket($requester, ['title' => 'Conflit entre deux membres']);

        // Menu de l'administration : le manager voit les deux entrées, l'administrateur global seulement l'annuaire.
        $this->actingAs($manager)->get('/admin')->assertOk()->assertSee('Tickets de mentorat')->assertSee('Mentors');
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Mentors')->assertDontSee('Tickets de mentorat');
        $this->actingAs($mentor)->get('/admin')->assertForbidden();

        // Les anciennes adresses du fil renvoient vers l'administration.
        $this->actingAs($manager)->get('/mentorat/gestion')->assertRedirect('/admin/mentorat');
        $this->actingAs($manager)->get('/mentorat/mentors')->assertRedirect('/admin/mentorat/mentors');

        // La fiche d'administration porte toutes les actions du manager.
        $this->actingAs($manager)->get(route('admin.mentoring.tickets.show', $ticket))->assertOk()
            ->assertSee('Conflit entre deux membres')->assertSee('Qualifier le ticket')->assertSee('name="ctx" value="admin"', false);

        // Les actions lancées depuis l'administration y ramènent le manager.
        $this->actingAs($manager)->post(route('mentoring.qualify', $ticket), ['category' => 'relations', 'priority' => 'high', 'ctx' => 'admin'])
            ->assertRedirect(route('admin.mentoring.tickets.show', $ticket));
        $this->actingAs($manager)->post(route('mentoring.assign', $ticket), ['mentor_id' => $mentor->id, 'ctx' => 'admin'])
            ->assertRedirect(route('admin.mentoring.tickets.show', $ticket));
        $this->actingAs($manager)->post(route('mentoring.comment', $ticket), ['body' => 'Point de situation'])
            ->assertRedirect(route('mentoring.show', $ticket).'#fil');

        // Le mentor affecté reste côté membre et y trouve le lien d'accompagnement, pas celui de gestion.
        $this->actingAs($mentor)->get(route('mentoring.show', $ticket))->assertOk()->assertDontSee('Ouvrir dans l’administration');
        $this->actingAs($manager)->get('/mentorat')->assertOk()->assertDontSee(route('admin.mentoring.tickets.index'), false);

        // Sa propre demande se suit côté membre, même pour un manager.
        $own = $this->ticket($manager);
        $this->actingAs($manager)->get(route('admin.mentoring.tickets.show', $own))->assertRedirect(route('mentoring.show', $own));
        $this->actingAs($manager)->get(route('mentoring.show', $own))->assertOk();

        // Module désactivé : plus rien dans l'administration.
        AppModule::query()->where('key', 'mentoring.tickets')->update(['is_active' => false]);
        $this->actingAs($manager)->get('/admin/mentorat')->assertForbidden();
        $this->actingAs($manager)->get('/admin')->assertForbidden();
    }

    /** @param array<string, array<int, string>> $matrix */
    private function holder(string $title, array $matrix): User
    {
        $user = User::factory()->create();
        $position = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'position', 'category' => 'direction', 'title' => $title, 'sort_order' => 0, 'module_permissions' => $matrix]);
        $person = \App\Models\OrgPerson::query()->create(['full_name' => $user->name, 'user_id' => $user->id]);
        \App\Models\OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);

        return $user->fresh();
    }

    public function test_navigation_link_and_notification_links(): void
    {
        $manager = $this->mentor(true);
        $member = User::factory()->create();

        $this->actingAs($member)->get('/notifications')->assertOk()->assertSee(route('mentoring.index'), false);

        $ticket = $this->ticket($member);
        $this->actingAs($manager)->get('/notifications')->assertOk()->assertSee(route('mentoring.show', $ticket, false), false);

        AppModule::query()->where('key', 'mentoring.tickets')->update(['is_active' => false]);
        $this->actingAs($member)->get('/notifications')->assertOk()->assertDontSee(route('mentoring.index'), false);
    }
}
