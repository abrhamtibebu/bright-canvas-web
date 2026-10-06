import { createFileRoute } from '@tanstack/react-router';
import { Projects } from '@/components/validity/workspace';
export const Route = createFileRoute('/projects/')({
 head: () => ({ meta: [{ title: 'Projects — Validity' }, { name: 'description', content: 'Plan and manage Validity event staffing projects.' }, { property: 'og:title', content: 'Projects — Validity' }, { property: 'og:description', content: 'Plan and manage Validity event staffing projects.' }, { property: 'og:type', content: 'website' }, { name: 'twitter:card', content: 'summary_large_image' }] }),
 component: Projects,
});
