<?php

namespace WebHappens\LaravelPo\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranslationsImported
{
    use Dispatchable, SerializesModels;

    /**
     * The locale for which translations were imported.
     *
     * @var string
     */
    public string $locale;

    /**
     * The translation groups and their imported keys.
     *
     * @var array<string, array<string>>
     */
    public array $groups;

    /**
     * Create a new event instance.
     *
     * @param  string  $locale
     * @param  array<string, array<string>>  $groups  An array of group names to arrays of imported keys
     */
    public function __construct(string $locale, array $groups)
    {
        $this->locale = $locale;
        $this->groups = $groups;
    }
}
