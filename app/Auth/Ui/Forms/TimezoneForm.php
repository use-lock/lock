<?php
declare(strict_types=1);

namespace App\Auth\Ui\Forms;

use Illuminate\Http\Request;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Form;
use Lattice\Form\Components\Select;
use Lattice\Form\FormData;
use Lattice\Http\LatticeResponse;

#[AsForm('profile.preferences.timezone')]
final class TimezoneForm extends ProfileForm
{
    public function definition(Form $form, Request $request): Form
    {
        $user = $this->currentUser();

        $timezones = timezone_identifiers_list();

        $common = [
            'UTC',
            'Europe/London',
            'Europe/Berlin',
            'Europe/Paris',
            'Europe/Madrid',
            'America/New_York',
            'America/Chicago',
            'America/Los_Angeles',
            'Asia/Tokyo',
            'Australia/Sydney',
        ];

        $defaultOptions = array_map(
            fn (string $tz) => Select::option($tz, $tz),
            array_values(array_intersect($common, $timezones)),
        );

        return $this->profileForm($form)
            ->fill(['timezone' => $user->timezone ?? config('app.timezone')])
            ->schema([
                Select::make('timezone', __('user.preferences.timezone.label'))
                    ->placeholder(__('user.preferences.timezone.placeholder'))
                    ->options($defaultOptions)
                    ->searchable(function (string $search) use ($timezones) {
                        $matches = $search === ''
                            ? $timezones
                            : array_filter(
                                $timezones,
                                fn (string $tz) => str_contains(strtolower($tz), strtolower($search)),
                            );

                        return array_map(fn (string $tz) => Select::option($tz, $tz), array_values($matches));
                    })
                    ->resolveSelectedUsing(fn (array $values) => array_map(
                        fn (string $tz) => Select::option($tz, $tz),
                        array_values(array_intersect($timezones, $values)),
                    ))
                    ->required()
                    ->rules(['required', 'timezone']),
            ]);
    }

    public function handle(FormData $data): LatticeResponse
    {
        $this->currentUser()->update(['timezone' => $data['timezone']]);

        return $this->saved(__('user.preferences.updated'));
    }
}
