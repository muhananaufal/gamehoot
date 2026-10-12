<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Packs\SavePackQuestion;
use App\Enums\GameType;
use App\Games\GameEngines;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * O10: shared steps of DemoSeeder and LoadTestSeeder. They run on staging, whose image has no
 * dev dependencies (P2), so they use the app's own actions and fixed lists instead of
 * factories and Faker, and every question passes the same validation as the host's form.
 */
final class SeedContent
{
    public const array FIRST_NAMES = [
        'Ana', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fajar', 'Gita', 'Hendra', 'Indah', 'Joko',
        'Kartika', 'Lukman', 'Maya', 'Nanda', 'Oki', 'Putri', 'Rizky', 'Sari', 'Taufik', 'Umi',
        'Vina', 'Wahyu', 'Yusuf', 'Zahra', 'Bunga',
    ];

    public const array LAST_NAMES = [
        'Santoso', 'Wulandari', 'Pratama', 'Lestari', 'Setiawan', 'Rahmawati', 'Nugroho', 'Permata',
        'Wijaya', 'Hakim', 'Saputra', 'Anggraini', 'Kusuma', 'Hidayat', 'Siregar', 'Halim',
        'Utami', 'Firmansyah', 'Maharani', 'Gunawan',
    ];

    /**
     * O10: never in production, whatever the class.
     */
    public static function refuseProduction(string $seeder): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException("{$seeder} does not run in production.");
        }
    }

    /**
     * Demo data belongs to the first super-admin, who is made at the prompt (O10).
     */
    public static function owner(): User
    {
        return User::query()->where('is_super_admin', true)->oldest()->first()
            ?? throw new RuntimeException('Create a super-admin first: php artisan pentahoot:create-super-admin');
    }

    /**
     * Distinct "First Last" names, in a stable order.
     *
     * @return list<string>
     */
    public static function names(int $count): array
    {
        $names = [];

        foreach (self::LAST_NAMES as $last) {
            foreach (self::FIRST_NAMES as $first) {
                $names[] = "{$first} {$last}";
            }
        }

        if ($count > count($names)) {
            throw new RuntimeException('Only '.count($names).' distinct names are available.');
        }

        return array_slice($names, 0, $count);
    }

    /**
     * A pack with its questions, each validated like the host's form (F13).
     *
     * @param  list<array<string, mixed>>  $questions
     */
    public static function pack(User $owner, GameType $type, string $title, array $questions): QuestionPack
    {
        $pack = new QuestionPack(['title' => $title, 'game_type' => $type]);
        $pack->owner()->associate($owner)->save();
        $engine = app(GameEngines::class)->for($type);

        foreach ($questions as $question) {
            $validated = Validator::make($question, $engine->questionRules(creating: true))->validate();
            app(SavePackQuestion::class)->handle($pack, null, $validated, $owner);
        }

        return $pack;
    }

    /**
     * A plain picture with a word on it, in the two sizes the browser would send (E8).
     *
     * @param  array{int<0, 255>, int<0, 255>, int<0, 255>}  $rgb
     * @return array{UploadedFile, UploadedFile}
     */
    public static function picture(string $word, array $rgb): array
    {
        return [self::canvas($word, $rgb, 1920, 1080), self::canvas($word, $rgb, 720, 405)];
    }

    /**
     * @param  array{int<0, 255>, int<0, 255>, int<0, 255>}  $rgb
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    private static function canvas(string $word, array $rgb, int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, ...$rgb);
        $ink = imagecolorallocate($image, 255, 255, 255);

        if ($background === false || $ink === false) {
            throw new RuntimeException('Could not draw the demo picture.');
        }

        imagefill($image, 0, 0, $background);
        imagestring($image, 5, intdiv($width, 2) - strlen($word) * 4, intdiv($height, 2) - 8, $word, $ink);

        $path = sys_get_temp_dir().'/pentahoot-demo-'.Str::random(32).'.jpg';
        imagejpeg($image, $path, 85);

        return new UploadedFile($path, "{$word}.jpg", 'image/jpeg', null, true);
    }
}
