<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { trans } from '@/lib/i18n';
import { edit } from '@/routes/profile';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: trans('Profile settings'),
                href: edit(),
            },
        ],
    }),
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const email = ref(user.value.email);
// Matches ProfileUpdateRequest::changesEmail(): the server asks for the password only then.
const changesEmail = computed(
    () => email.value.trim().toLowerCase() !== user.value.email.toLowerCase(),
);
</script>

<template>
    <Head :title="$t('Profile settings')" />

    <h1 class="sr-only">{{ $t('Profile settings') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('Profile')"
            :description="$t('Update your name and email address')"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ $t('Name') }}</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    :placeholder="$t('Full name')"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ $t('Email address') }}</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    v-model="email"
                    required
                    autocomplete="username"
                    :placeholder="$t('Email address')"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="changesEmail" class="grid gap-2">
                <Label for="current_password">{{
                    $t('Current password')
                }}</Label>
                <Input
                    id="current_password"
                    type="password"
                    class="mt-1 block w-full"
                    name="current_password"
                    required
                    autocomplete="current-password"
                    aria-describedby="current-password-hint"
                />
                <p
                    id="current-password-hint"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('Changing your email address needs your password.') }}
                </p>
                <InputError class="mt-2" :message="errors.current_password" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                    >{{ $t('Save') }}</Button
                >
            </div>
        </Form>
    </div>
</template>
