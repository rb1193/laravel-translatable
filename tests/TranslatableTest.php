<?php

use Astrotomic\Translatable\Locales;
use Astrotomic\Translatable\Test\Model\Food;
use Astrotomic\Translatable\Test\Model\Person;
use Astrotomic\Translatable\Test\Model\Country;
use Astrotomic\Translatable\Test\Model\CountryStrict;
use Astrotomic\Translatable\Test\Model\CountryTranslation;
use Astrotomic\Translatable\Test\Model\CountryWithCustomLocaleKey;
use Astrotomic\Translatable\Test\Model\CountryWithCustomTranslationModel;
use Astrotomic\Translatable\Test\Model\Vegetable;
use Astrotomic\Translatable\Test\Model\VegetableTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class TranslatableTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_finds_the_default_translation_class()
    {
        $this->assertEquals(
            VegetableTranslation::class, 
            (new Vegetable())->getTranslationModelNameDefault()
        );
    }

    /** @test */
    public function it_finds_the_translation_class_with_namespace_set()
    {
        $this->app->make('config')->set('translatable.translation_model_namespace', 'App\Models\Translations');

        $this->assertEquals(
            'App\Models\Translations\VegetableTranslation',
            (new Vegetable())->getTranslationModelNameDefault()
        );
    }

    /** @test */
    public function it_finds_the_translation_class_with_suffix_set()
    {
        $this->app->make('config')->set('translatable.translation_suffix', 'Trans');

        $this->assertEquals(
            'Astrotomic\Translatable\Test\Model\VegetableTrans',
            (new Vegetable())->getTranslationModelName()
        );
    }

    /** @test */
    public function it_returns_custom_TranslationModelName()
    {
        $vegetable = new Vegetable();

        $this->assertEquals(
            $vegetable->getTranslationModelNameDefault(),
            $vegetable->getTranslationModelName()
        );

        $vegetable->translationModel = 'MyAwesomeVegetableTranslation';
        $this->assertEquals(
            'MyAwesomeVegetableTranslation',
            $vegetable->getTranslationModelName()
        );
    }

    /** @test */
    public function it_returns_relation_key()
    {
        $vegetable = new Vegetable();
        $this->assertEquals('vegetable_identity', $vegetable->getRelationKey());

        $vegetable->translationForeignKey = 'my_awesome_key';
        $this->assertEquals('my_awesome_key', $vegetable->getRelationKey());
    }

    /** @test */
    public function it_returns_the_translation()
    {
        $vegetable = factory(Vegetable::class)->create(['name:el' => 'Αρακάς','name:en' => 'Peas']);

        $this->assertEquals('Αρακάς', $vegetable->translate('el')->name);

        $this->assertEquals('Peas', $vegetable->translate('en')->name);

        $this->app->setLocale('el');
        $this->assertEquals('Αρακάς', $vegetable->translate()->name);

        $this->app->setLocale('en');
        $this->assertEquals('Peas', $vegetable->translate()->name);
    }

    /** @test */
    public function it_returns_the_translation_with_accessor()
    {
        $vegetable = factory(Vegetable::class)->create(['name:el' => 'Αρακάς', 'name:en' => 'Peas']);

        $this->assertEquals('Αρακάς', $vegetable->{'name:el'});
        $this->assertEquals('Peas', $vegetable->{'name:en'});
    }

    /** @test */
    public function it_returns_null_when_the_locale_doesnt_exist()
    {
        $vegetable = factory(Vegetable::class)->create(['name:el' => 'Αρακάς']);

        $this->assertSame(null, $vegetable->{'name:unknown-locale'});
    }

    /** @test */
    public function it_saves_translations()
    {
        $vegetable = factory(Vegetable::class)->create(['name:el' => 'Αρακάς', 'name:en' => 'Peas']);

        $this->assertEquals('Peas', $vegetable->name);

        $vegetable->name = 'Pea';
        $vegetable->save();
        $vegetable->refresh();
        
        $this->assertEquals('Pea', $vegetable->name);
    }

    /** @test */
    public function it_saves_translations_with_mutator()
    {
        $vegetable = factory(Vegetable::class)->create(['name:el' => 'Αρακάς', 'name:en' => 'Peas']);

        $vegetable->{'name:en'} = 'Pea';
        $vegetable->{'name:el'} = 'Μπιζέλι';
        $vegetable->save();
        $vegetable->refresh();

        $this->app->setLocale('en');
        $this->assertEquals('Pea', $vegetable->translate()->name);

        $this->app->setLocale('el');
        $this->assertEquals('Μπιζέλι', $vegetable->translate()->name);
    }

    /** @test */
    public function it_does_not_lazy_load_translations_when_updating_non_translated_attributes()
    {
        DB::enableQueryLog();

        $vegetable = factory(Vegetable::class)->create();
        $this->assertFalse($vegetable->relationLoaded('translations'));
        $this->assertCount(1, DB::getQueryLog());

        DB::flushQueryLog();
        
        $vegetable->update(['quantity' => 5]);
        $this->assertFalse($vegetable->relationLoaded('translations'));
        $this->assertCount(1, DB::getQueryLog());
        
        DB::flushQueryLog();
        
        $vegetable->update(['name' => 'Germany']);
        $this->assertTrue($vegetable->relationLoaded('translations'));
        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();
    }

    /** @test */
    public function it_uses_default_locale_to_return_translations()
    {
        $vegetable = factory(Vegetable::class)->create(['name:el' => 'Αρακάς']);

        $vegetable->translate('el')->name = 'Μπιζέλι';

        $this->app->setLocale('el');
        $this->assertEquals('Μπιζέλι', $vegetable->name);
        $vegetable->save();

        $vegetable->refresh();
        $this->assertEquals('Μπιζέλι', $vegetable->translate('el')->name);
    }

    /** @test */
    public function it_creates_translations_using_the_shortcut()
    {
        $vegetable = factory(Vegetable::class)->create();

        $vegetable->name = 'Peas';
        $vegetable->save();

        $vegetable = Vegetable::first();
        $this->assertEquals('Peas', $vegetable->name);
        $this->assertDatabaseHas('vegetable_translations', [
            'vegetable_identity' => $vegetable->identity,
            'locale' => 'en',
            'name' => 'Peas'
        ]);
    }

    /** @test */
    public function it_creates_translations_using_mass_assignment()
    {
        $vegetable = Vegetable::create([
            'quantity' => 5,
            'name' => 'Peas',
        ]);

        $this->assertEquals(5, $vegetable->quantity);
        $this->assertEquals('Peas', $vegetable->name);
    }

    /** @test */
    public function it_creates_translations_using_mass_assignment_and_locales()
    {
        $vegetable = Vegetable::create([
            'quantity' => 5,
            'en'   => ['name' => 'Peas'],
            'fr'   => ['name' => 'Pois'],
        ]);

        $this->assertEquals(5, $vegetable->quantity);
        $this->assertEquals('Peas', $vegetable->translate('en')->name);
        $this->assertEquals('Pois', $vegetable->translate('fr')->name);

        $vegetable = Vegetable::first();
        $this->assertEquals('Peas', $vegetable->translate('en')->name);
        $this->assertEquals('Pois', $vegetable->translate('fr')->name);
    }

    /** @test */
    public function it_skips_mass_assignment_if_attributes_non_fillable()
    {
        $this->expectException(Illuminate\Database\Eloquent\MassAssignmentException::class);
        $country = CountryStrict::create([
            'code' => 'be',
            'en'   => ['name' => 'Belgium'],
            'fr'   => ['name' => 'Belgique'],
        ]);

        $this->assertEquals('be', $country->code);
        $this->assertNull($country->translate('en'));
        $this->assertNull($country->translate('fr'));
    }

    /** @test */
    public function it_returns_if_object_has_translation()
    {
        $vegetable = factory(Vegetable::class)->create(['name:en' => 'Peas']);

        $this->assertTrue($vegetable->hasTranslation('en'));
        $this->assertFalse($vegetable->hasTranslation('some-code'));
    }

    /** @test */
    public function it_returns_default_translation()
    {
        $this->app->make('config')->set('translatable.fallback_locale', 'de');

        $vegetable = factory(Vegetable::class)->create(['name:de' => 'Erbsen']);

        $this->assertEquals('Erbsen', $vegetable->getTranslation('ch', true)->name);
        $this->assertEquals('Erbsen', $vegetable->translateOrDefault('ch')->name);
        $this->assertNull($vegetable->getTranslation('ch', false));

        $this->app->setLocale('ch');
        $this->assertSame('Erbsen', $vegetable->translateOrDefault()->name);
    }

    /** @test */
    public function fallback_option_in_config_overrides_models_fallback_option()
    {
        $this->app->make('config')->set('translatable.fallback_locale', 'de');

        $vegetable = factory(Vegetable::class)->create(['name:de' => 'Erbsen']);
        $this->assertEquals('de', $vegetable->getTranslation('ch', true)->locale);

        $vegetable->useTranslationFallback = false;
        $this->assertEquals('de', $vegetable->getTranslation('ch', true)->locale);

        $vegetable->useTranslationFallback = true;
        $this->assertEquals('de', $vegetable->getTranslation('ch')->locale);

        $vegetable->useTranslationFallback = false;
        $this->assertNull($vegetable->getTranslation('ch'));
    }

    /** @test */
    public function configuration_defines_if_fallback_is_used()
    {
        $this->app->make('config')->set('translatable.fallback_locale', 'de');
        $this->app->make('config')->set('translatable.use_fallback', true);

        $vegetable = factory(Vegetable::class)->create(['name:de' => 'Erbsen']);

        $this->assertEquals('de', $vegetable->getTranslation('ch')->locale);
    }

    /** @test */
    public function useTranslationFallback_overrides_configuration()
    {
        $this->app->make('config')->set('translatable.fallback_locale', 'de');
        $this->app->make('config')->set('translatable.use_fallback', true);

        $vegetable = factory(Vegetable::class)->create(['name:en' => 'Peas']);
        $vegetable->useTranslationFallback = false;

        $this->assertNull($vegetable->getTranslation('ch'));
    }

    /** @test */
    public function it_returns_null_if_fallback_is_not_defined()
    {
        $this->app->make('config')->set('translatable.fallback_locale', 'ch');

        $vegetable = factory(Vegetable::class)->create(['name:en' => 'Peas']);

        $this->assertNull($vegetable->getTranslation('pl', true));
    }

    /** @test */
    public function it_fills_a_non_default_language_with_fallback_set()
    {
        $this->app->make('config')->set('translatable.fallback_locale', 'en');

        $vegetable = new Vegetable();
        $vegetable->fill([
            'quantity' => 5,
            'en'   => ['name' => 'Peas'],
            'de'   => ['name' => 'Erbsen'],
        ]);

        $this->assertEquals('Peas', $vegetable->translate('en')->name);
    }

    /** @test */
    public function it_creates_a_new_translation()
    {
        $this->app->make('config')->set('translatable.fallback_locale', 'en');

        $vegetable = factory(Vegetable::class)->create();
        $vegetable->getNewTranslation('en')->name = 'Peas';
        $vegetable->save();

        $this->assertEquals('Peas', $vegetable->translate('en')->name);
    }

    /** @test */
    public function the_locale_key_is_locale_by_default()
    {
        $vegetable = new Vegetable();

        $this->assertEquals('locale', $vegetable->getLocaleKey());
    }

    /** @test */
    public function the_locale_key_can_be_overridden_in_configuration()
    {
        $this->app->make('config')->set('translatable.locale_key', 'language_id');

        $vegetable = new Vegetable();
        $this->assertEquals('language_id', $vegetable->getLocaleKey());
    }

    /** @test */
    public function the_locale_key_can_be_customized_per_model()
    {
        $vegetable = new Vegetable();
        $vegetable->localeKey = 'language_id';
        $this->assertEquals('language_id', $vegetable->getLocaleKey());
    }

    public function test_the_translation_model_can_be_customized()
    {
        $country = CountryWithCustomTranslationModel::create([
            'code' => 'es',
            'name:en' => 'Spain',
            'name:de' => 'Spanien',
        ]);
        $this->assertTrue($country->exists());
        $this->assertEquals($country->translate('en')->name, 'Spain');
        $this->assertEquals($country->translate('de')->name, 'Spanien');
    }

    /** @test */
    public function it_reads_the_configuration()
    {
        $this->assertEquals('Translation', $this->app->make('config')->get('translatable.translation_suffix'));
    }

    /** @test */
    public function getting_translation_does_not_create_translation()
    {
        $vegetable = factory(Vegetable::class)->create();

        $this->assertNull($vegetable->getTranslation('en', false));
    }

    /** @test */
    public function getting_translated_field_does_not_create_translation()
    {
        $this->app->setLocale('en');
        $vegetable = factory(Vegetable::class)->create();

        $this->assertNull($vegetable->getTranslation('en'));
    }

    /** @test */
    public function it_has_methods_that_return_always_a_translation()
    {
        $vegetable = factory(Vegetable::class)->create();
        $this->assertEquals('abc', $vegetable->translateOrNew('abc')->locale);

        $this->app->setLocale('xyz');
        $this->assertEquals('xyz', $vegetable->translateOrNew()->locale);
    }

    /** @test */
    public function it_returns_if_attribute_is_translated()
    {
        $vegetable = new Vegetable();

        $this->assertTrue($vegetable->isTranslationAttribute('name'));
        $this->assertFalse($vegetable->isTranslationAttribute('some-field'));
    }

    /** @test */
    public function config_overrides_apps_locale()
    {
        $veegtable = factory(Vegetable::class)->create(['name:de' => 'Erbsen']);
        App::make('config')->set('translatable.locale', 'de');

        $this->assertEquals('Erbsen', $veegtable->name);
    }

    /** @test */
    public function locales_as_array_keys_are_properly_detected()
    {
        $this->app->config->set('translatable.locales', ['en' => ['US', 'GB']]);

        $frenchFries = Food::create([
            'en'    => ['name' => 'French fries'],
            'en-US' => ['name' => 'American french fries'],
            'en-GB' => ['name' => 'Chips'],
        ]);

        $this->assertEquals('French fries', $frenchFries->getTranslation('en')->name);
        $this->assertEquals('Chips', $frenchFries->getTranslation('en-GB')->name);
        $this->assertEquals('American french fries', $frenchFries->getTranslation('en-US')->name);
    }

    /** @test */
    public function locale_separator_can_be_configured()
    {
        $this->app->make('config')->set('translatable.locales', ['en' => ['GB']]);
        $this->app->make('config')->set('translatable.locale_separator', '_');
        $this->app->make('translatable.locales')->load();
        $vegetable = Vegetable::create([
            'en_GB' => ['name' => 'Peas'],
        ]);

        $this->assertEquals('Peas', $vegetable->getTranslation('en_GB')->name);
    }

    public function test_fallback_for_country_based_locales()
    {
        $this->app->config->set('translatable.use_fallback', true);
        $this->app->config->set('translatable.fallback_locale', 'fr');
        $this->app->config->set('translatable.locales', ['en' => ['US', 'GB'], 'fr']);
        $this->app->config->set('translatable.locale_separator', '-');
        $this->app->make('translatable.locales')->load();
        $data = [
            'id'    => 1,
            'fr'    => ['name' => 'frites'],
            'en-GB' => ['name' => 'chips'],
            'en'    => ['name' => 'french fries'],
        ];
        Food::create($data);
        $fries = Food::find(1);
        $this->assertSame('french fries', $fries->getTranslation('en-US')->name);
    }

    public function test_fallback_for_country_based_locales_with_no_base_locale()
    {
        $this->app->config->set('translatable.use_fallback', true);
        $this->app->config->set('translatable.fallback_locale', 'en');
        $this->app->config->set('translatable.locales', ['pt' => ['PT', 'BR'], 'en']);
        $this->app->config->set('translatable.locale_separator', '-');
        $this->app->make('translatable.locales')->load();
        $data = [
            'id'    => 1,
            'en'    => ['name' => 'chips'],
            'pt-PT' => ['name' => 'batatas fritas'],
        ];
        Food::create($data);
        $fries = Food::find(1);
        $this->assertSame('chips', $fries->getTranslation('pt-BR')->name);
    }

    public function test_to_array_and_fallback_with_country_based_locales_enabled()
    {
        $this->app->config->set('translatable.locale', 'en-GB');
        $this->app->config->set('translatable.use_fallback', true);
        $this->app->config->set('translatable.fallback_locale', 'fr');
        $this->app->config->set('translatable.locales', ['en' => ['GB'], 'fr']);
        $this->app->config->set('translatable.locale_separator', '-');
        $this->app->make('translatable.locales')->load();
        $data = [
            'id' => 1,
            'fr' => ['name' => 'frites'],
        ];
        Food::create($data);
        $fritesArray = Food::find(1)->toArray();
        $this->assertSame('frites', $fritesArray['name']);
    }

    public function test_it_skips_translations_in_to_array_when_config_is_set()
    {
        $this->app->config->set('translatable.to_array_always_loads_translations', false);
        $greece = Country::whereCode('gr')->first()->toArray();
        $this->assertFalse(isset($greece['name']));
    }

    public function test_it_returns_translations_in_to_array_when_config_is_set_but_translations_are_loaded()
    {
        $this->app->config->set('translatable.to_array_always_loads_translations', false);
        $greece = Country::whereCode('gr')->with('translations')->first()->toArray();
        $this->assertTrue(isset($greece['name']));
    }

    public function test_it_should_mutate_the_translated_attribute_if_a_mutator_is_set_on_model()
    {
        $person = new Person(['name' => 'john doe']);
        $person->save();
        $person = Person::find(1);
        $this->assertEquals('John doe', $person->name);
    }

    public function test_it_deletes_all_translations()
    {
        $country = Country::whereCode('gr')->first();
        $this->assertSame(4, count($country->translations));

        $country->deleteTranslations();

        $this->assertSame(0, count($country->translations));
        $country = Country::whereCode('gr')->first();
        $this->assertSame(0, count($country->translations));
    }

    public function test_it_deletes_translations_for_given_locales()
    {
        $country = Country::whereCode('gr')->with('translations')->first();
        $count = count($country->translations);

        $country->deleteTranslations('fr');

        $this->assertSame($count - 1, count($country->translations));
        $country = Country::whereCode('gr')->with('translations')->first();
        $this->assertSame($count - 1, count($country->translations));
        $this->assertSame(null, $country->translate('fr'));
    }

    public function test_passing_an_empty_array_should_not_delete_translations()
    {
        $country = Country::whereCode('gr')->with('translations')->first();
        $count = count($country->translations);

        $country->deleteTranslations([]);

        $country = Country::whereCode('gr')->with('translations')->first();
        $this->assertSame($count, count($country->translations));
    }

    public function test_fill_with_translation_key()
    {
        $country = new Country();
        $country->fill([
            'code'    => 'tr',
            'name:en' => 'Turkey',
            'name:de' => 'Türkei',
        ]);
        $this->assertEquals($country->translate('en')->name, 'Turkey');
        $this->assertEquals($country->translate('de')->name, 'Türkei');

        $country->save();
        $country = Country::whereCode('tr')->first();
        $this->assertEquals($country->translate('en')->name, 'Turkey');
        $this->assertEquals($country->translate('de')->name, 'Türkei');
    }

    public function test_it_uses_the_default_locale_from_the_model()
    {
        $country = new Country();
        $country->fill([
            'code'    => 'tn',
            'name:en' => 'Tunisia',
            'name:fr' => 'Tunisie',
        ]);
        $this->assertEquals($country->name, 'Tunisia');
        $country->setDefaultLocale('fr');
        $this->assertEquals($country->name, 'Tunisie');

        $country->setDefaultLocale(null);
        $country->save();
        $country = Country::whereCode('tn')->first();
        $this->assertEquals($country->name, 'Tunisia');
        $country->setDefaultLocale('fr');
        $this->assertEquals($country->name, 'Tunisie');
    }

    public function test_replicate_entity()
    {
        $apple = new Food();
        $apple->fill([
            'name:fr' => 'Pomme',
            'name:en' => 'Apple',
            'name:de' => 'Apfel',
        ]);
        $apple->save();

        $replicatedApple = $apple->replicateWithTranslations();
        $this->assertNotSame($replicatedApple->id, $apple->id);
        $this->assertEquals($replicatedApple->translate('fr')->name, $apple->translate('fr')->name);
        $this->assertEquals($replicatedApple->translate('en')->name, $apple->translate('en')->name);
        $this->assertEquals($replicatedApple->translate('de')->name, $apple->translate('de')->name);
    }

    public function test_getTranslationsArray()
    {
        Country::create([
            'code'    => 'tn',
            'name:en' => 'Tunisia',
            'name:fr' => 'Tunisie',
            'name:de' => 'Tunesien',
        ]);

        /** @var Country $country */
        $country = Country::where('code', 'tn')->first();

        $this->assertSame([
            'de' => ['name' => 'Tunesien'],
            'en' => ['name' => 'Tunisia'],
            'fr' => ['name' => 'Tunisie'],
        ], $country->getTranslationsArray());
    }

    public function test_fill_when_locale_key_unknown()
    {
        config(['translatable.locales' => ['en']]);

        $country = new Country();
        $country->fill([
            'code' => 'ua',
            'en'   => ['name' => 'Ukraine'],
            'ua'   => ['name' => 'Україна'], // "ua" is unknown, so must be ignored
        ]);

        $modelTranslations = [];

        foreach ($country->translations as $translation) {
            foreach ($country->translatedAttributes as $attr) {
                $modelTranslations[$translation->locale][$attr] = $translation->{$attr};
            }
        }

        $expectedTranslations = [
            'en' => ['name' => 'Ukraine'],
        ];

        $this->assertEquals($modelTranslations, $expectedTranslations);
    }

    public function test_fill_with_translation_key_when_locale_key_unknown()
    {
        config(['translatable.locales' => ['en']]);

        $country = new Country();
        $country->fill([
            'code'    => 'ua',
            'name:en' => 'Ukraine',
            'name:ua' => 'Україна', // "ua" is unknown, so must be ignored
        ]);

        $modelTranslations = [];

        foreach ($country->translations as $translation) {
            foreach ($country->translatedAttributes as $attr) {
                $modelTranslations[$translation->locale][$attr] = $translation->{$attr};
            }
        }

        $expectedTranslations = [
            'en' => ['name' => 'Ukraine'],
        ];

        $this->assertEquals($modelTranslations, $expectedTranslations);
    }

    public function test_it_uses_fallback_locale_if_default_is_empty()
    {
        App::make('config')->set('translatable.use_fallback', true);
        App::make('config')->set('translatable.use_property_fallback', true);
        App::make('config')->set('translatable.fallback_locale', 'en');
        $country = new Country();
        $country->fill([
            'code'    => 'tn',
            'name:en' => 'Tunisia',
            'name:fr' => '',
        ]);
        $this->app->setLocale('en');
        $this->assertEquals('Tunisia', $country->name);
        $this->app->setLocale('fr');
        $this->assertEquals('Tunisia', $country->name);
    }

    public function test_it_always_uses_value_when_fallback_not_available()
    {
        App::make('config')->set('translatable.fallback_locale', 'it');
        App::make('config')->set('translatable.use_fallback', true);

        $country = new Country();
        $country->fill([
            'code' => 'gr',
            'en' => ['name' => ''],
            'de' => ['name' => 'Griechenland'],
        ]);

        // verify translated attributed is correctly returned when empty (non-existing fallback is ignored)
        $this->app->setLocale('en');
        $this->assertEquals('', $country->getAttribute('name'));

        $this->app->setLocale('de');
        $this->assertEquals('Griechenland', $country->getAttribute('name'));
    }

    public function test_translation_with_multiconnection()
    {
        $this->migrate('mysql2');

        // Add country & translation in second db
        $country = new Country();
        $country->setConnection('mysql2');
        $country->code = 'sg';
        $country->{'name:sg'} = 'Singapore';
        $this->assertTrue($country->save());

        $countryId = $country->id;

        // Verify added country & translation in second db
        $country = new Country();
        $country->setConnection('mysql2');
        $sgCountry2 = $country->newQuery()->find($countryId);
        $this->assertEquals('Singapore', $sgCountry2->translate('sg')->name);

        // Verify added country not in default db
        $country = new Country();
        $country->setConnection('mysql');
        $sgCountry1 = $country->newQuery()->where('code', 'sg')->get();
        $this->assertEmpty($sgCountry1);

        // Verify added translation not in default db
        $country = new Country();
        $country->setConnection('mysql');
        $sgCountry1 = $country->newQuery()->find($countryId);
        $this->assertEmpty($sgCountry1->translate('sg'));
    }

    public function test_empty_translated_attribute()
    {
        $country = Country::whereCode('gr')->first();
        $this->app->setLocale('invalid');
        $this->assertSame(null, $country->name);
    }

    public function test_numeric_translated_attribute()
    {
        $this->app->config->set('translatable.fallback_locale', 'de');
        $this->app->config->set('translatable.use_fallback', true);

        $city = new class extends \Astrotomic\Translatable\Test\Model\City {
            protected $fillable = [
                'country_id',
            ];
            protected $table = 'cities';
            public $translationModel = \Astrotomic\Translatable\Test\Model\CityTranslation::class;
            public $translationForeignKey = 'city_id';

            protected function isEmptyTranslatableAttribute(string $key, $value): bool
            {
                if ($key === 'name') {
                    return is_null($value);
                }

                return empty($value);
            }
        };
        $city->fill([
            'country_id' => Country::first()->getKey(),
            'en' => ['name' => '0'],
            'de' => ['name' => '1'],
            'fr' => ['name' => null],
        ]);
        $city->save();

        $this->app->setLocale('en');
        $this->assertSame('0', $city->name);

        $this->app->setLocale('fr');
        $this->assertSame('1', $city->name);
    }

    public function test_translation_relation()
    {
        $this->app->make('config')->set('translatable.fallback_locale', 'fr');
        $this->app->make('config')->set('translatable.use_fallback', true);
        $this->app->setLocale('en');

        $translation = Country::find(1)->translation;
        $this->assertInstanceOf(CountryTranslation::class, $translation);
        $this->assertEquals('en', $translation->locale);
    }

    public function test_translation_relation_fallback()
    {
        $this->app->make('config')->set('translatable.fallback_locale', 'fr');
        $this->app->make('config')->set('translatable.use_fallback', true);
        $this->app->setLocale('xyz');

        $translation = Country::find(1)->translation;
        $this->assertInstanceOf(CountryTranslation::class, $translation);
        $this->assertEquals('fr', $translation->locale);
    }

    public function test_translation_relation_not_found()
    {
        $this->app->make('config')->set('translatable.fallback_locale', 'xyz');
        $this->app->make('config')->set('translatable.use_fallback', true);
        $this->app->setLocale('xyz');

        $translation = Country::find(1)->translation;
        $this->assertNull($translation);
    }

    public function test_can_fill_conflicting_attribute_locale()
    {
        config(['translatable.locales' => ['en', 'id']]);
        $this->app->make(\Astrotomic\Translatable\Locales::class)->load();

        $city = new class extends \Astrotomic\Translatable\Test\Model\City {
            protected $guarded = [];
            protected $table = 'cities';
            public $translationModel = \Astrotomic\Translatable\Test\Model\CityTranslation::class;
            public $translationForeignKey = 'city_id';
        };

        $city->fill([
            'country_id' => Country::first()->getKey(),
            'id' => [
                'name' => 'id:my city',
            ],
            'en' => [
                'name' => 'en:my city',
            ],
        ]);

        $city->fill([
            'id' => 100,
        ]);

        $city->save();

        $this->assertEquals(100, $city->getKey());
        $this->assertEquals('id:my city', $city->getTranslation('id', false)->name);
        $this->assertEquals('en:my city', $city->getTranslation('en', false)->name);
    }

    public function test_it_returns_first_existing_translation_as_fallback()
    {
        /** @var Locales $helper */
        $helper = $this->app->make(Locales::class);

        $this->app->make('config')->set('translatable.locales', [
            'xyz',
            'en',
            'de' => [
                'DE',
                'AT',
            ],
            'fr',
            'el',
        ]);
        $this->app->make('config')->set('translatable.fallback_locale', null);
        $this->app->make('config')->set('translatable.use_fallback', true);
        $this->app->setLocale('xyz');
        $helper->load();

        CountryTranslation::create([
            'country_id' => 1,
            'locale' => $helper->getCountryLocale('de', 'DE'),
            'name' => 'Griechenland',
        ]);

        /** @var Country $country */
        $country = Country::find(1);
        $this->assertNull($country->getTranslation(null, false));

        // returns first existing locale
        $translation = $country->getTranslation();
        $this->assertInstanceOf(CountryTranslation::class, $translation);
        $this->assertEquals('en', $translation->locale);

        // still returns simple locale for country based locale
        $translation = $country->getTranslation($helper->getCountryLocale('de', 'AT'));
        $this->assertInstanceOf(CountryTranslation::class, $translation);
        $this->assertEquals('de', $translation->locale);

        $this->app->make('config')->set('translatable.locales', [
            'xyz',
            'de' => [
                'DE',
                'AT',
            ],
            'en',
            'fr',
            'el',
        ]);
        $helper->load();

        // returns simple locale before country based locale
        $translation = $country->getTranslation();
        $this->assertInstanceOf(CountryTranslation::class, $translation);
        $this->assertEquals('de', $translation->locale);

        $country->translations()->where('locale', 'de')->delete();
        $country->unsetRelation('translations');

        // returns country based locale before next simple one
        $translation = $country->getTranslation();
        $this->assertInstanceOf(CountryTranslation::class, $translation);
        $this->assertEquals($helper->getCountryLocale('de', 'DE'), $translation->locale);
    }

    public function test_it_uses_translation_relation_if_locale_matches()
    {
        $this->app->make('config')->set('translatable.use_fallback', false);
        $this->app->setLocale('de');

        /** @var Country $country */
        $country = Country::find(1);
        $country->load('translation');
        $this->assertTrue($country->relationLoaded('translation'));
        $this->assertFalse($country->relationLoaded('translations'));

        $translation = $country->getTranslation();
        $this->assertInstanceOf(CountryTranslation::class, $translation);
        $this->assertEquals('de', $translation->locale);
        $this->assertFalse($country->relationLoaded('translations'));
    }

    public function test_it_uses_translations_relation_if_locale_does_not_match()
    {
        $this->app->make('config')->set('translatable.use_fallback', false);
        $this->app->setLocale('de');

        /** @var Country $country */
        $country = Country::find(1);
        $country->load('translation');
        $this->assertTrue($country->relationLoaded('translation'));
        $this->assertFalse($country->relationLoaded('translations'));
        $this->app->setLocale('en');

        $translation = $country->getTranslation();
        $this->assertInstanceOf(CountryTranslation::class, $translation);
        $this->assertEquals('en', $translation->locale);
        $this->assertTrue($country->relationLoaded('translations'));
    }

    public function test_it_does_not_load_translation_relation_if_not_already_loaded()
    {
        $this->app->make('config')->set('translatable.use_fallback', false);
        $this->app->setLocale('de');

        /** @var Country $country */
        $country = Country::find(1);
        $this->assertFalse($country->relationLoaded('translation'));
        $this->assertFalse($country->relationLoaded('translations'));

        $translation = $country->getTranslation();
        $this->assertInstanceOf(CountryTranslation::class, $translation);
        $this->assertEquals('de', $translation->locale);
        $this->assertFalse($country->relationLoaded('translation'));
        $this->assertTrue($country->relationLoaded('translations'));
    }
}
