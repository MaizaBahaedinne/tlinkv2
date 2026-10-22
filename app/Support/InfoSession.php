<?php

namespace App\Support;

use App\Models\QuizAttempt;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Info session (vidéo) suivie d'un quiz : le compte d'un membre est validé quand le quiz est réussi.
 * Le contenu (vidéo, questions, durée, seuil) se règle dans l'administration et vit en base.
 * Les questions et les réponses sont mélangées à chaque tentative ; les bonnes réponses restent côté serveur.
 */
class InfoSession
{
    private const GROUP = 'onboarding';

    private const KEY = 'info_session';

    /** Délai de grâce (secondes) entre la fin du compte à rebours et la réception de l'envoi. */
    public const GRACE_SECONDS = 30;

    public const MAX_QUESTIONS = 30;

    public const MAX_OPTIONS = 8;

    /**
     * @return array{video_url: string, title: string, intro: string, duration_minutes: int, pass_percent: int, questions: array<int, array<string, mixed>>}
     */
    public static function defaults(): array
    {
        $question = static fn (string $id, string $text, string $type, array $options): array => [
            'id' => $id,
            'text' => $text,
            'type' => $type,
            'options' => array_map(static fn (array $option): array => ['text' => $option[0], 'correct' => $option[1]], $options),
        ];

        return [
            'video_url' => 'https://www.youtube.com/watch?v=QwrBWK07RnU',
            'title' => 'Info session et quiz',
            'intro' => "Tunivisions Foundation est offerte par Media Visions Editing. C'est une communauté de plus de 157 universités et 40 clubs lycéens à travers le pays, qui propose des expériences où les jeunes apprennent par la pratique. Les étudiants réalisent des événements et des projets concrets dans des domaines comme la culture, l'art, le sport et la citoyenneté, dans un environnement positif qui les encourage à prendre des rôles de leadership. Ils vivent l'expérience des Tunimateurs dans chaque club du pays, grâce à des programmes de formation durables, aux clubs universitaires et au camp Tunivisions.",
            'duration_minutes' => 8,
            'pass_percent' => 100,
            'questions' => [
                $question('q1', 'En quelle année la Fondation Tunivisions a-t-elle vu le jour ?', 'single', [['2016', true], ['1995', false], ['2017', false], ['1997', false]]),
                $question('q2', 'Quelle est la nature de Tunivisions Foundation ?', 'single', [['Un magazine', false], ['Une start-up', false], ['Un programme de RSE', false], ['Une fondation nationale', true]]),
                $question('q3', 'Où la Fondation Tunivisions implante-t-elle ses clubs ?', 'single', [
                    ['Dans les régions, les écoles primaires, les lycées et les facultés', false],
                    ['Dans les écoles primaires, les lycées et les facultés', false],
                    ['Dans les lycées et les facultés', true],
                    ['Dans les régions, les lycées et les facultés', false],
                ]),
                $question('q4', 'Quelles sont les trois valeurs défendues par la Fondation ?', 'single', [
                    ['Innovation, partage, croissance', false],
                    ['Innovation, travail, partage', true],
                    ['Partage, travail, créativité', false],
                    ['Innovation, compétence, partage', false],
                ]),
                $question('q5', 'Parmi ces propositions, lesquelles font partie des missions de la Fondation ? (plusieurs réponses possibles)', 'multiple', [
                    ['Former les jeunes aux soft skills', false],
                    ['Promouvoir la culture entrepreneuriale', true],
                    ['Organiser des actions de charité', true],
                    ['Mettre en valeur les régions', true],
                ]),
                $question('q6', 'Quelle est la vision de la Fondation Tunivisions ?', 'single', [
                    ['Faire émerger de jeunes leaders', false],
                    ['Faire émerger de jeunes leaders tunisiens', true],
                    ['Organiser des événements et des conférences', false],
                ]),
            ],
        ];
    }

    /**
     * Réglages enregistrés, complétés par les valeurs par défaut.
     *
     * @return array{video_url: string, title: string, intro: string, duration_minutes: int, pass_percent: int, questions: array<int, array<string, mixed>>}
     */
    public static function settings(): array
    {
        $stored = json_decode((string) Setting::query()->where('group', self::GROUP)->where('key', self::KEY)->value('value'), true);

        return is_array($stored) ? array_merge(self::defaults(), $stored) : self::defaults();
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function save(array $settings): void
    {
        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::KEY],
            ['value' => json_encode($settings, JSON_UNESCAPED_UNICODE)],
        );
    }

    /**
     * Identifiant YouTube (11 caractères) d'un lien watch, youtu.be ou embed ; null si le lien n'est pas YouTube.
     */
    public static function videoId(?string $url): ?string
    {
        if ($url === null || ! preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com|youtube-nocookie\.com|youtu\.be)/~i', trim($url))) {
            return null;
        }

        return preg_match('~(?:[?&]v=|youtu\.be/|/embed/)([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])~', $url, $matches) ? $matches[1] : null;
    }

    /**
     * Nettoie les questions saisies dans l'administration : ignore les lignes vides et vérifie la cohérence.
     *
     * @param array<int|string, mixed> $raw
     * @return array<int, array{id: string, text: string, type: string, options: array<int, array{text: string, correct: bool}>}>
     */
    public static function normalizeQuestions(array $raw): array
    {
        $questions = [];
        $errors = [];
        $position = 0;

        foreach ($raw as $item) {
            if (! is_array($item) || trim((string) ($item['text'] ?? '')) === '') {
                continue;
            }

            $position++;
            $type = ($item['type'] ?? 'single') === 'multiple' ? 'multiple' : 'single';
            $options = [];

            foreach (is_array($item['options'] ?? null) ? $item['options'] : [] as $option) {
                $text = trim((string) ($option['text'] ?? ''));

                if ($text !== '') {
                    $options[] = ['text' => mb_substr($text, 0, 300), 'correct' => ! empty($option['correct'])];
                }
            }

            $correct = count(array_filter($options, fn (array $option): bool => $option['correct']));

            if (count($options) < 2) {
                $errors["questions.$position"] = "Question $position : au moins 2 réponses sont nécessaires.";
            } elseif ($correct === 0) {
                $errors["questions.$position"] = "Question $position : cochez au moins une bonne réponse.";
            } elseif ($type === 'single' && $correct > 1) {
                $errors["questions.$position"] = "Question $position : une question à réponse unique n’a qu’une bonne réponse (sinon choisissez « plusieurs réponses »).";
            }

            $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($item['id'] ?? '')) ?: 'q'.bin2hex(random_bytes(3));

            $questions[] = ['id' => mb_substr($id, 0, 20), 'text' => mb_substr(trim((string) $item['text']), 0, 500), 'type' => $type, 'options' => array_slice($options, 0, self::MAX_OPTIONS)];
        }

        if ($questions === []) {
            $errors['questions'] = 'Ajoutez au moins une question.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $questions;
    }

    /**
     * Ouvre une tentative : questions et réponses mélangées, bonnes réponses figées dans l'instantané.
     * Une tentative encore ouverte est reprise (ordre et chronomètre conservés en cas d'actualisation de la page).
     */
    public static function start(User $user): QuizAttempt
    {
        self::closeExpired($user);

        $open = QuizAttempt::query()->where('user_id', $user->id)->where('status', 'in_progress')->latest('id')->first();

        if ($open) {
            return $open;
        }

        $settings = self::settings();
        $questions = $settings['questions'];
        shuffle($questions);
        $shown = [];
        $correct = [];

        foreach ($questions as $question) {
            $options = $question['options'];
            shuffle($options);
            $shownOptions = [];

            foreach ($options as $option) {
                $optionId = bin2hex(random_bytes(4));
                $shownOptions[] = ['id' => $optionId, 'text' => $option['text']];

                if ($option['correct']) {
                    $correct[$question['id']][] = $optionId;
                }
            }

            $shown[] = ['id' => $question['id'], 'text' => $question['text'], 'type' => $question['type'], 'options' => $shownOptions];
        }

        return QuizAttempt::query()->create([
            'user_id' => $user->id,
            'status' => 'in_progress',
            'snapshot' => ['questions' => $shown, 'correct' => $correct, 'pass_percent' => (int) $settings['pass_percent']],
            'total' => count($shown),
            'started_at' => now(),
            'expires_at' => now()->addMinutes(max(1, (int) $settings['duration_minutes'])),
        ]);
    }

    /**
     * Clôture les tentatives dont le temps est écoulé depuis plus que le délai de grâce.
     */
    public static function closeExpired(User $user): void
    {
        QuizAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->where('expires_at', '<', now()->subSeconds(self::GRACE_SECONDS))
            ->update(['status' => 'expired', 'score' => 0, 'submitted_at' => now()]);
    }

    /**
     * Corrige la tentative. Une question à choix multiple doit avoir exactement les bonnes réponses.
     *
     * @param array<string, mixed> $answers question => identifiants des réponses cochées
     */
    public static function submit(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        return DB::transaction(function () use ($attempt, $answers): QuizAttempt {
            $attempt = QuizAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if (! $attempt->isOpen()) {
                return $attempt;
            }

            if (now()->greaterThan($attempt->expires_at->copy()->addSeconds(self::GRACE_SECONDS))) {
                $attempt->update(['status' => 'expired', 'score' => 0, 'submitted_at' => now()]);

                return $attempt;
            }

            $snapshot = $attempt->snapshot;
            $clean = [];
            $score = 0;

            foreach ($snapshot['questions'] as $question) {
                $valid = array_column($question['options'], 'id');
                $given = $answers[$question['id']] ?? [];
                $given = array_values(array_unique(array_filter(is_array($given) ? $given : [(string) $given], fn ($id): bool => is_string($id) && in_array($id, $valid, true))));
                $clean[$question['id']] = $given;

                $expected = $snapshot['correct'][$question['id']] ?? [];
                sort($given);
                sort($expected);

                if ($given !== [] && $given === $expected) {
                    $score++;
                }
            }

            $passed = $attempt->total > 0 && $score * 100 >= (int) $snapshot['pass_percent'] * $attempt->total;

            $attempt->update([
                'status' => $passed ? 'passed' : 'failed',
                'answers' => $clean,
                'score' => $score,
                'submitted_at' => now(),
            ]);

            if ($passed) {
                $user = User::query()->find($attempt->user_id);

                if ($user && $user->onboarding_completed_at === null) {
                    $user->forceFill(['onboarding_completed_at' => now()])->save();
                    AuditLogger::record($user->id, 'infosession.passed', User::class, $user->id, ['score' => $score, 'total' => $attempt->total]);
                }
            }

            return $attempt;
        });
    }
}
