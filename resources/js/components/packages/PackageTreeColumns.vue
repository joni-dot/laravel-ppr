<script setup lang="ts">
/**
 * The column widths and headings of the package table, shared with its loading placeholder.
 */
defineProps<{
    numberColumnWidth: string;
}>();
</script>

<template>
    <!-- Fixed widths, so the columns stay put while rows come and go during scrolling. -->
    <colgroup>
        <col class="font-mono" :style="{ width: numberColumnWidth }" />
        <col />
        <col class="w-28" />
        <col class="w-24" />
        <col class="w-40" />
        <col class="w-28" />
        <col class="w-28" />
        <col class="w-20" />
        <col class="w-28" />
        <col class="w-32" />
        <col class="w-32" />
        <col class="w-26" />
    </colgroup>
    <thead class="bg-muted/50 text-left text-muted-foreground">
        <tr aria-rowindex="1">
            <th scope="col" class="px-4 py-3 font-medium">
                {{ $t('Number') }}
            </th>
            <th scope="col" class="px-4 py-3 font-medium">
                {{ $t('Title') }}
            </th>
            <th scope="col" class="px-4 py-3 font-medium">
                {{ $t('Status') }}
            </th>
            <th scope="col" class="px-4 py-3 font-medium">
                {{ $t('Risk') }}
            </th>
            <th scope="col" class="px-4 py-3 font-medium">
                {{ $t('Next milestone') }}
            </th>
            <th scope="col" class="px-4 py-3 font-medium">
                {{ $t('Need date') }}
            </th>
            <th scope="col" class="px-4 py-3 font-medium">
                {{ $t('Expected') }}
            </th>
            <th scope="col" class="px-4 py-3 text-right font-medium">
                {{ $t('Float') }}
            </th>
            <th scope="col" class="px-4 py-3 font-medium">
                {{ $t('Type') }}
            </th>
            <th scope="col" class="px-4 py-3 font-medium">
                {{ $t('Owner') }}
            </th>
            <th scope="col" class="px-4 py-3 font-medium">
                {{ $t('Buyer') }}
            </th>
            <th scope="col" class="px-4 py-3">
                <span class="sr-only">{{ $t('Actions') }}</span>
            </th>
        </tr>
    </thead>
</template>
