import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AppLogo from '@/components/AppLogo.vue';
import AuthSimpleLayout from '@/layouts/auth/AuthSimpleLayout.vue';
import type { Organization } from '@/types';

const page = vi.hoisted(() => ({
    props: { name: 'Uplift', organization: null as Organization | null },
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    Link: { template: '<a><slot /></a>' },
}));

vi.mock('@/routes', () => ({ home: () => '/' }));

const acme: Organization = {
    name: 'Acme',
    logo_url: '/organization/logo?v=abc',
    accent_color: null,
    student_term: 'Student',
    teacher_term: 'Teacher',
    leaderboard_enabled: true,
    grading_scale: 'scale_0_10',
};

const global = { mocks: { $t: (key: string) => key } };

describe.each([
    ['app shell', AppLogo],
    ['login header', AuthSimpleLayout],
])('logo in the %s (DATA-011)', (_, component) => {
    beforeEach(() => {
        page.props.organization = null;
    });

    it('shows the organisation logo when it has one', () => {
        page.props.organization = acme;
        const wrapper = mount(component, { global });

        const logo = wrapper.find('[data-test="organization-logo"]');
        expect(logo.attributes('src')).toBe('/organization/logo?v=abc');
        expect(logo.attributes('alt')).toBe('Acme');
        expect(wrapper.find('[data-test="app-logo"]').exists()).toBe(false);
    });

    it('shows the app logo without an organisation logo', () => {
        page.props.organization = { ...acme, logo_url: null };
        const wrapper = mount(component, { global });

        expect(wrapper.find('[data-test="organization-logo"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-test="app-logo"]').exists()).toBe(true);
    });

    it('shows the app logo outside an organisation', () => {
        const wrapper = mount(component, { global });

        expect(wrapper.find('[data-test="app-logo"]').exists()).toBe(true);
    });
});
