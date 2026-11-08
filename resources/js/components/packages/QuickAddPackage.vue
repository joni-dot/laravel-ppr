<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { onMounted, useTemplateRef } from 'vue';
import PackageController from '@/actions/App/Http/Controllers/Projects/PackageController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import type { ProjectCategory } from '@/types';

/**
 * A one-line form: type a title, press Enter, and the package is added with the next number.
 * The form stays open with the same category, so the next package can be typed right away.
 */
const props = defineProps<{
    projectId: number;
    categories: ProjectCategory[];
    // Add a sub-package under this package instead of a top-level package.
    parent?: { id: number; number: string; categoryId: number | null };
    // Top-level packages need a category when it is part of their number.
    categoryRequired?: boolean;
    autofocus?: boolean;
    // Shows a "Done" button, for a form that opens on request.
    closable?: boolean;
    // The page props to reload after adding; all of them when left out. A deferred prop must be named here,
    // or the page drops it and shows its placeholder until it has loaded again.
    reloadOnly?: string[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const titleInput = useTemplateRef<InstanceType<typeof Input>>('title');

const form = useForm({
    title: '',
    category_id: props.parent ? props.parent.categoryId : null,
    parent_id: props.parent?.id ?? null,
});

const idPrefix = props.parent
    ? `quick-add-${props.parent.id}`
    : 'quick-add-package';

function focusTitle(): void {
    (titleInput.value?.$el as HTMLInputElement | undefined)?.focus();
}

function add(): void {
    form.post(PackageController.store.url(props.projectId), {
        preserveScroll: true,
        preserveState: true,
        only: props.reloadOnly ?? [],
        onSuccess: () => {
            form.reset('title');
            focusTitle();
        },
    });
}

onMounted(() => {
    if (props.autofocus) {
        focusTitle();
    }
});
</script>

<template>
    <form class="grid gap-2" @submit.prevent="add" @keydown.esc="emit('close')">
        <div class="flex flex-wrap items-start gap-2">
            <Input
                :id="`${idPrefix}-title`"
                ref="title"
                v-model="form.title"
                required
                maxlength="200"
                autocomplete="off"
                :placeholder="
                    parent
                        ? $t('Title of the new sub-package')
                        : $t('Title of the new package')
                "
                :aria-label="
                    parent
                        ? $t('Title of a new sub-package under :number', {
                              number: parent.number,
                          })
                        : $t('Title of the new package')
                "
                :aria-invalid="form.errors.title ? true : undefined"
                class="min-w-48 flex-1"
            />
            <NativeSelect
                :id="`${idPrefix}-category`"
                v-model="form.category_id"
                :required="!parent && categoryRequired"
                :aria-label="$t('Category')"
                :aria-invalid="form.errors.category_id ? true : undefined"
                class="w-56"
            >
                <option v-if="parent || !categoryRequired" :value="null">
                    {{ $t('No category') }}
                </option>
                <option v-else :value="null" disabled>
                    {{ $t('Choose a category') }}
                </option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id"
                >
                    {{
                        $t(':code – :name', {
                            code: category.code,
                            name: category.name,
                        })
                    }}
                </option>
            </NativeSelect>
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                <Plus v-else />
                {{ parent ? $t('Add sub-package') : $t('Add package') }}
            </Button>
            <Button
                v-if="closable"
                type="button"
                variant="ghost"
                @click="emit('close')"
            >
                {{ $t('Done') }}
            </Button>
        </div>
        <InputError
            :message="
                form.errors.title ??
                form.errors.category_id ??
                form.errors.parent_id
            "
        />
    </form>
</template>
