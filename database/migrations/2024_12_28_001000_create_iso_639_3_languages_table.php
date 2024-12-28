<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('iso_639_3_languages', function (Blueprint $table) {
            $table->string('id', 3)->primary();  // The ISO 639-3 identifier
            $table->timestamps();
            $table->string('part2b', 3)->nullable();  // ISO 639-2 bibliographic code
            $table->string('part2t', 3)->nullable();  // ISO 639-2 terminology code
            $table->string('part1', 2)->nullable();   // ISO 639-1 code if available
            $table->string('scope', 1);               // I(ndividual), M(acrolanguage), S(pecial)
            $table->string('type', 1);                // A(ncient), C(onstructed), E(xtinct), H(istorical), L(iving), S(pecial)
            $table->string('ref_name', 150);          // Reference name
            $table->string('comment', 150)->nullable();
            $table->string('flag_code', 2);
        });

        $commonLanguages = [
            ['id' => 'eng', 'part2b' => 'eng', 'part2t' => 'eng', 'part1' => 'en', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'English', 'flag_code' => 'gb'],
            ['id' => 'jpn', 'part2b' => 'jpn', 'part2t' => 'jpn', 'part1' => 'ja', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Japanese', 'flag_code' => 'jp'],
            ['id' => 'kor', 'part2b' => 'kor', 'part2t' => 'kor', 'part1' => 'ko', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Korean', 'flag_code' => 'kr'],
            ['id' => 'rus', 'part2b' => 'rus', 'part2t' => 'rus', 'part1' => 'ru', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Russian', 'flag_code' => 'ru'],
            ['id' => 'ukr', 'part2b' => 'ukr', 'part2t' => 'ukr', 'part1' => 'uk', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Ukrainian', 'flag_code' => 'ua'],

            // Portuguese variants
            ['id' => 'por', 'part2b' => 'por', 'part2t' => 'por', 'part1' => 'pt', 'scope' => 'M', 'type' => 'L', 'ref_name' => 'Portuguese', 'flag_code' => 'pt'],
            ['id' => 'pob', 'part2b' => null, 'part2t' => null, 'part1' => null, 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Brazilian Portuguese', 'flag_code' => 'br'],

            // Spanish variants
            ['id' => 'spa', 'part2b' => 'spa', 'part2t' => 'spa', 'part1' => 'es', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Spanish', 'flag_code' => 'es'],
            ['id' => 'esm', 'part2b' => null, 'part2t' => null, 'part1' => null, 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Mexican Spanish', 'flag_code' => 'mx'],

            // Chinese variants (already have main Chinese, adding variants)
            ['id' => 'cmn', 'part2b' => null, 'part2t' => null, 'part1' => null, 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Mandarin Chinese', 'flag_code' => 'cn'],
            ['id' => 'yue', 'part2b' => null, 'part2t' => null, 'part1' => null, 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Cantonese', 'flag_code' => 'hk'],
            ['id' => 'zho', 'part2b' => 'chi', 'part2t' => 'zho', 'part1' => 'zh', 'scope' => 'M', 'type' => 'L', 'ref_name' => 'Chinese', 'flag_code' => 'cn'],

            // European languages
            ['id' => 'deu', 'part2b' => 'ger', 'part2t' => 'deu', 'part1' => 'de', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'German', 'flag_code' => 'de'],
            ['id' => 'fra', 'part2b' => 'fre', 'part2t' => 'fra', 'part1' => 'fr', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'French', 'flag_code' => 'fr'],
            ['id' => 'nld', 'part2b' => 'dut', 'part2t' => 'nld', 'part1' => 'nl', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Dutch', 'flag_code' => 'nl'],
            ['id' => 'swe', 'part2b' => 'swe', 'part2t' => 'swe', 'part1' => 'sv', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Swedish', 'flag_code' => 'se'],
            ['id' => 'nor', 'part2b' => 'nor', 'part2t' => 'nor', 'part1' => 'no', 'scope' => 'M', 'type' => 'L', 'ref_name' => 'Norwegian', 'flag_code' => 'no'],
            ['id' => 'fin', 'part2b' => 'fin', 'part2t' => 'fin', 'part1' => 'fi', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Finnish', 'flag_code' => 'fi'],
            ['id' => 'dan', 'part2b' => 'dan', 'part2t' => 'dan', 'part1' => 'da', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Danish', 'flag_code' => 'dk'],
            ['id' => 'pol', 'part2b' => 'pol', 'part2t' => 'pol', 'part1' => 'pl', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Polish', 'flag_code' => 'pl'],
            ['id' => 'ces', 'part2b' => 'cze', 'part2t' => 'ces', 'part1' => 'cs', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Czech', 'flag_code' => 'cz'],
            ['id' => 'slk', 'part2b' => 'slo', 'part2t' => 'slk', 'part1' => 'sk', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Slovak', 'flag_code' => 'sk'],
            ['id' => 'hun', 'part2b' => 'hun', 'part2t' => 'hun', 'part1' => 'hu', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Hungarian', 'flag_code' => 'hu'],
            ['id' => 'ron', 'part2b' => 'rum', 'part2t' => 'ron', 'part1' => 'ro', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Romanian', 'flag_code' => 'ro'],
            ['id' => 'bul', 'part2b' => 'bul', 'part2t' => 'bul', 'part1' => 'bg', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Bulgarian', 'flag_code' => 'bg'],
            ['id' => 'ell', 'part2b' => 'gre', 'part2t' => 'ell', 'part1' => 'el', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Greek', 'flag_code' => 'gr'],

            // Asian languages
            ['id' => 'hin', 'part2b' => 'hin', 'part2t' => 'hin', 'part1' => 'hi', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Hindi', 'flag_code' => 'in'],
            ['id' => 'tha', 'part2b' => 'tha', 'part2t' => 'tha', 'part1' => 'th', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Thai', 'flag_code' => 'th'],
            ['id' => 'vie', 'part2b' => 'vie', 'part2t' => 'vie', 'part1' => 'vi', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Vietnamese', 'flag_code' => 'vn'],
            ['id' => 'ind', 'part2b' => 'ind', 'part2t' => 'ind', 'part1' => 'id', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Indonesian', 'flag_code' => 'id'],
            ['id' => 'msa', 'part2b' => 'may', 'part2t' => 'msa', 'part1' => 'ms', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Malay', 'flag_code' => 'my'],
            ['id' => 'fil', 'part2b' => null, 'part2t' => null, 'part1' => null, 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Filipino', 'flag_code' => 'ph'],

            // Middle Eastern languages
            ['id' => 'ara', 'part2b' => 'ara', 'part2t' => 'ara', 'part1' => 'ar', 'scope' => 'M', 'type' => 'L', 'ref_name' => 'Arabic', 'flag_code' => 'sa'],
            ['id' => 'fas', 'part2b' => 'per', 'part2t' => 'fas', 'part1' => 'fa', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Persian', 'flag_code' => 'ir'],
            ['id' => 'tur', 'part2b' => 'tur', 'part2t' => 'tur', 'part1' => 'tr', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Turkish', 'flag_code' => 'tr'],
            ['id' => 'heb', 'part2b' => 'heb', 'part2t' => 'heb', 'part1' => 'he', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'Hebrew', 'flag_code' => 'il'],
        ];

        foreach ($commonLanguages as $lang) {
            $lang['created_at'] = now();
            $lang['updated_at'] = now();
            DB::table('iso_639_3_languages')->insert($lang);
        }
    }

    public function down()
    {
        Schema::dropIfExists('iso_639_3_languages');
    }
};
