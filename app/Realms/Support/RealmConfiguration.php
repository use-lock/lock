<?php

declare(strict_types=1);

namespace App\Realms\Support;

use App\Realms\Data\RealmSetting;
use App\Realms\Data\RealmSettings;
use App\Realms\Enums\RealmSettingSection;
use App\Realms\Enums\RealmSettingType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Lock\Server\Shared\Realms\Settings\LoginMethod;
use Lock\Server\Shared\Realms\Settings\MfaRequirement;
use Lock\Server\Shared\Scopes\ScopeRepository;

final class RealmConfiguration
{
    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return RealmSettings::defaults()->toArray();
    }

    /**
     * @return array<string, RealmSetting>
     */
    public static function settings(): array
    {
        $settings = [];

        foreach (self::sectionTypes() as $section => $types) {
            foreach ($types as $name => $type) {
                $settings[$name] = new RealmSetting($name, RealmSettingSection::from($section), $type);
            }
        }

        return $settings;
    }

    public static function setting(string $name): ?RealmSetting
    {
        return self::settings()[$name] ?? null;
    }

    /**
     * @return array<string, list<RealmSetting>>
     */
    public static function sections(): array
    {
        $sections = [];

        foreach (self::settings() as $setting) {
            $sections[$setting->section->value][] = $setting;
        }

        return $sections;
    }

    /**
     * So a rule failing on a setting the user is not looking at still names it
     * the way the console does.
     *
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        $names = [];

        foreach (self::settings() as $setting) {
            $names[$setting->name] = __('realms.fields.'.$setting->translationKey().'.label');
        }

        return $names;
    }

    /** @return array<string, list<string|In>> */
    public static function rules(): array
    {
        return [
            'access_token_lifetime' => ['required', 'integer', 'min:1', 'max:31536000'],
            'id_token_lifetime' => ['required', 'integer', 'min:1', 'max:31536000'],
            'client_credentials_lifetime' => ['required', 'integer', 'min:1', 'max:31536000'],
            'refresh_token_lifetime' => ['required', 'integer', 'min:1', 'max:31536000'],
            'session_absolute_lifetime' => ['required', 'integer', 'min:1', 'max:31536000'],
            'session_token_ttl' => ['required', 'integer', 'min:1', 'max:31536000', 'lte:session_absolute_lifetime'],
            'session_token_refresh_skew' => ['required', 'integer', 'min:0', 'lt:session_token_ttl'],
            'login_methods' => ['required', 'array', 'list', 'min:1', 'max:3'],
            'login_methods.*' => ['required', 'string', 'distinct', Rule::in(array_column(LoginMethod::cases(), 'value'))],
            'email_verification_required' => ['required', 'boolean'],
            'link_by_verified_email' => ['required', 'boolean'],
            'auto_provision' => ['required', 'boolean'],
            'mfa_requirement' => ['required', 'string', Rule::in(array_column(MfaRequirement::cases(), 'value'))],
            'challenge_providers' => ['required', 'array', 'list', 'min:1', 'max:2'],
            'challenge_providers.*' => ['required', 'string', 'distinct', Rule::in(['totp', 'webauthn'])],
            'totp_secret_length' => ['required', 'integer', 'min:16', 'max:64', 'multiple_of:8'],
            'totp_window' => ['required', 'integer', 'min:0', 'max:10'],
            'recovery_codes' => ['required', 'integer', 'min:1', 'max:20'],
            'password_min_length' => ['required', 'integer', 'min:6', 'max:128'],
            'password_mixed_case' => ['required', 'boolean'],
            'password_numbers' => ['required', 'boolean'],
            'password_symbols' => ['required', 'boolean'],
            'password_uncompromised' => ['required', 'boolean'],
            'password_history' => ['required', 'integer', 'min:0', 'max:24'],
            'password_max_age_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'dynamic_registration' => ['required', 'boolean'],
            'allowed_redirect_schemes' => ['present', 'array', 'list', 'max:100'],
            'allowed_redirect_schemes.*' => ['required', 'string', 'max:64', 'distinct', 'regex:/^[a-z][a-z0-9+.-]*$/', 'not_in:javascript,data,vbscript,file'],
            'allowed_redirect_domains' => ['present', 'array', 'list', 'max:100'],
            'allowed_redirect_domains.*' => ['required', 'string', 'max:253', 'distinct', 'regex:/\A(\*|[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[a-f0-9:]+\])\z/'],
            'default_scopes' => ['present', 'array', 'list', 'max:100'],
            'default_scopes.*' => ['required', 'string', 'max:255', 'distinct', Rule::in(self::catalogScopes())],
            'optional_scopes' => ['present', 'array', 'list', 'max:100'],
            'optional_scopes.*' => ['required', 'string', 'max:255', 'distinct', Rule::in(['*', ...self::catalogScopes()])],
            'token_exchange' => ['required', 'boolean'],
            'first_party_trusted' => ['required', 'boolean'],
            'trusted_clients' => ['present', 'array', 'list', 'max:100'],
            'trusted_clients.*' => ['required', 'string', 'max:255', 'distinct'],
        ];
    }

    /**
     * Validates a whole configuration — a submission merged into the realm's
     * current values, or into {@see defaults()} for a realm being created — so
     * the rules comparing two settings see both of them. The realm is named
     * rather than passed: a client it trusts has to be one of its own, and on a
     * create the slug is known before the row is.
     *
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    public static function validate(array $configuration, string $realmSlug): array
    {
        return Validator::make($configuration, [
            ...self::rules(),
            'trusted_clients.*' => [
                ...self::rules()['trusted_clients.*'],
                Rule::exists('oidc_clients', 'client_id')->where('realm', $realmSlug)->whereNull('revoked_at'),
            ],
        ])->setAttributeNames(self::attributeNames())->validate();
    }

    /**
     * @return array<string, array<string, RealmSettingType>>
     */
    private static function sectionTypes(): array
    {
        return [
            'tokens' => [
                'access_token_lifetime' => RealmSettingType::Duration,
                'id_token_lifetime' => RealmSettingType::Duration,
                'client_credentials_lifetime' => RealmSettingType::Duration,
                'refresh_token_lifetime' => RealmSettingType::Duration,
            ],
            'sessions' => [
                'session_absolute_lifetime' => RealmSettingType::Duration,
                'session_token_ttl' => RealmSettingType::Duration,
                'session_token_refresh_skew' => RealmSettingType::Duration,
            ],
            'login' => [
                'login_methods' => RealmSettingType::LoginMethods,
                'email_verification_required' => RealmSettingType::Flag,
            ],
            'social' => [
                'link_by_verified_email' => RealmSettingType::Flag,
                'auto_provision' => RealmSettingType::Flag,
            ],
            'mfa' => [
                'mfa_requirement' => RealmSettingType::MfaRequirement,
                'challenge_providers' => RealmSettingType::Factors,
                'totp_secret_length' => RealmSettingType::Count,
                'totp_window' => RealmSettingType::Count,
                'recovery_codes' => RealmSettingType::Count,
            ],
            'passwords' => [
                'password_min_length' => RealmSettingType::Count,
                'password_history' => RealmSettingType::Count,
                'password_max_age_days' => RealmSettingType::Days,
                'password_mixed_case' => RealmSettingType::Flag,
                'password_numbers' => RealmSettingType::Flag,
                'password_symbols' => RealmSettingType::Flag,
                'password_uncompromised' => RealmSettingType::Flag,
            ],
            'clients' => [
                'dynamic_registration' => RealmSettingType::Flag,
                'token_exchange' => RealmSettingType::Flag,
                'first_party_trusted' => RealmSettingType::Flag,
                'allowed_redirect_schemes' => RealmSettingType::StringList,
                'allowed_redirect_domains' => RealmSettingType::StringList,
                'default_scopes' => RealmSettingType::StringList,
                'optional_scopes' => RealmSettingType::StringList,
                'trusted_clients' => RealmSettingType::StringList,
            ],
        ];
    }

    /** @return list<string> */
    private static function catalogScopes(): array
    {
        return array_values(array_map(strval(...), app(ScopeRepository::class)->all()->pluck('id')->all()));
    }
}
