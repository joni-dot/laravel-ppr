<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AcceptInvitationController from '@/actions/App/Http/Controllers/Auth/AcceptInvitationController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { trans } from '@/lib/i18n';
import { login } from '@/routes';

defineOptions({
    layout: () => ({
        title: trans('Welcome aboard'),
        description: () =>
            trans('Choose a password to finish setting up your account'),
    }),
});

defineProps<{
    token: string;
    email: string;
    isValid: boolean;
    passwordRules: string;
}>();
</script>

<template>
    <Head :title="$t('Set your password')" />

    <div v-if="!isValid" class="space-y-4 text-center text-sm">
        <p>
            {{
                $t(
                    'This invitation link is invalid or has expired. Ask an admin to send you a new one.',
                )
            }}
        </p>
        <TextLink :href="login()">{{ $t('Go to login') }}</TextLink>
    </div>

    <Form
        v-else
        v-bind="AcceptInvitationController.store.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{ $t('Email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    autocomplete="username"
                    :default-value="email"
                    readonly
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ $t('Password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    autofocus
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{
                    $t('Confirm password')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button type="submit" class="mt-4 w-full" :disabled="processing">
                <Spinner v-if="processing" />
                {{ $t('Set password') }}
            </Button>
        </div>
    </Form>
</template>
