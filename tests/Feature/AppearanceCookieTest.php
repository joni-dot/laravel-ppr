<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppearanceCookieTest extends TestCase
{
    public function test_a_valid_appearance_is_used()
    {
        $this->withUnencryptedCookie('appearance', 'dark')
            ->get(route('login'))
            ->assertViewHas('appearance', 'dark');
    }

    public function test_an_unknown_appearance_falls_back_to_system()
    {
        $this->withUnencryptedCookie('appearance', "dark';alert(1)//")
            ->get(route('login'))
            ->assertViewHas('appearance', 'system')
            ->assertDontSee('alert(1)', escape: false);
    }
}
