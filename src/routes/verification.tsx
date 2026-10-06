import { createFileRoute } from '@tanstack/react-router';
import { Verification } from '@/components/validity/workspace';
export const Route = createFileRoute('/verification')({
 head: () => ({ meta: [{ title: 'Profile Verification — Validity' }, { name: 'description', content: 'Review and approve sample usher profiles.' }, { property: 'og:title', content: 'Profile Verification — Validity' }, { property: 'og:description', content: 'Review and approve sample usher profiles.' }, { property: 'og:type', content: 'website' }, { name: 'twitter:card', content: 'summary_large_image' }] }),
 component: Verification,
});
