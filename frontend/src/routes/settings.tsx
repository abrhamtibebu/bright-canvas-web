import { createFileRoute } from '@tanstack/react-router';
import { SettingsPage } from '@/components/validity/workspace';
export const Route = createFileRoute('/settings')({
 head: () => ({ meta: [{ title: 'Workspace Settings — Validity' }, { name: 'description', content: 'Validity organization and preview workspace settings.' }, { property: 'og:title', content: 'Workspace Settings — Validity' }, { property: 'og:description', content: 'Validity organization and preview workspace settings.' }, { property: 'og:type', content: 'website' }, { name: 'twitter:card', content: 'summary_large_image' }] }),
 component: SettingsPage,
});
