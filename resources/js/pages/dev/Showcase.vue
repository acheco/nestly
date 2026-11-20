<script setup lang="ts">
// Spec 001, R6 (D-102, D-103): dev-only showcase of the building blocks in both themes.
// Variant, size, state and toast type names are shown as code identifiers (the real prop
// values), not as interface text, so they don't go through t().
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { toast } from 'vue-sonner';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import type { ButtonVariants } from '@/components/ui/button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';

type Variant = NonNullable<ButtonVariants['variant']>;
type Size = NonNullable<ButtonVariants['size']>;

const variants: Variant[] = [
    'default',
    'destructive',
    'outline',
    'secondary',
    'ghost',
    'link',
];
const states = { disabled: 'disabled', loading: 'loading' } as const;
const textSizes: Size[] = ['sm', 'default', 'lg'];
const iconSizes: Size[] = ['icon-sm', 'icon', 'icon-lg'];

// Sample data, not interface text.
const accounts = [
    { name: 'Banreservas', amount: '45230.50', currency: 'DOP' },
    { name: 'Visa dólares', amount: '-1240.00', currency: 'USD' },
    {
        name: 'Cuenta de ahorro para la universidad de los niños',
        amount: '0.00',
        currency: 'DOP',
    },
];

// The amounts tested in tests/js/money.test.ts, so the browser test can reuse their output.
const amounts = [
    { amount: '999999999999.99', currency: 'DOP' },
    { amount: '999999999999.99', currency: 'USD' },
    { amount: '-1234.5', currency: 'DOP' },
    { amount: '-1234.5', currency: 'USD' },
];

const isNegative = (amount: string): boolean => amount.startsWith('-');

const toasts = [
    { type: 'success', message: t('The changes were saved.') },
    { type: 'error', message: t('The changes could not be saved.') },
    { type: 'info', message: t('There is new information available.') },
    { type: 'warning', message: t('Review the data before continuing.') },
] as const;
</script>

<template>
    <Head :title="t('Showcase')" />

    <div class="flex flex-col gap-10 p-4">
        <h1 class="text-2xl font-semibold tracking-tight">
            {{ t('Showcase') }}
        </h1>

        <section class="space-y-4">
            <Heading variant="small" :title="t('Appearance')" />
            <AppearanceTabs />
        </section>

        <section class="space-y-4">
            <Heading variant="small" :title="t('Buttons')" />

            <div
                v-for="variant in variants"
                :key="variant"
                class="flex flex-wrap items-center gap-3"
            >
                <code class="text-muted-foreground w-28 text-sm">{{
                    variant
                }}</code>
                <Button :variant="variant">{{ variant }}</Button>
                <Button :variant="variant" disabled>{{
                    states.disabled
                }}</Button>
                <Button :variant="variant" disabled>
                    <Spinner />
                    {{ states.loading }}
                </Button>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Button v-for="size in textSizes" :key="size" :size="size">
                    {{ size }}
                </Button>
                <Button
                    v-for="size in iconSizes"
                    :key="size"
                    :size="size"
                    :aria-label="t('Add')"
                >
                    <Plus />
                </Button>
            </div>
        </section>

        <section class="space-y-4">
            <Heading variant="small" :title="t('Cards')" />

            <div class="grid gap-4 md:grid-cols-3">
                <Card v-for="account in accounts" :key="account.name">
                    <CardHeader>
                        <CardTitle>{{ account.name }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-1">
                        <p
                            class="font-amount text-2xl"
                            :class="{
                                'text-expense': isNegative(account.amount),
                            }"
                        >
                            {{ formatMoney(account.amount, account.currency) }}
                        </p>
                        <p class="text-muted-foreground text-sm">
                            {{ t('Available balance') }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </section>

        <section class="space-y-4">
            <Heading variant="small" :title="t('Amounts')" />

            <ul class="space-y-2">
                <li
                    v-for="item in amounts"
                    :key="`${item.currency}${item.amount}`"
                    class="font-amount text-lg"
                    :class="{ 'text-expense': isNegative(item.amount) }"
                >
                    {{ formatMoney(item.amount, item.currency) }}
                </li>
            </ul>
        </section>

        <section class="max-w-sm space-y-4">
            <Heading variant="small" :title="t('Fields')" />

            <div class="grid gap-2">
                <Label for="showcase-name">{{ t('Name') }}</Label>
                <Input id="showcase-name" default-value="Ana Pérez" />
            </div>

            <div class="grid gap-2">
                <Label for="showcase-email">{{ t('Email address') }}</Label>
                <Input
                    id="showcase-email"
                    default-value="ana@"
                    aria-invalid="true"
                    aria-describedby="showcase-email-error"
                />
                <InputError
                    id="showcase-email-error"
                    :message="t('Enter a valid email address.')"
                />
            </div>
        </section>

        <section class="space-y-4">
            <Heading variant="small" :title="t('Notifications')" />

            <div class="flex flex-wrap gap-3">
                <Button
                    v-for="item in toasts"
                    :key="item.type"
                    variant="outline"
                    :data-test="`toast-${item.type}`"
                    @click="toast[item.type](item.message)"
                >
                    {{ item.type }}
                </Button>
            </div>
        </section>
    </div>
</template>
