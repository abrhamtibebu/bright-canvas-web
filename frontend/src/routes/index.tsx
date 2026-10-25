import { createFileRoute } from '@tanstack/react-router';
import { Overview } from '@/components/validity/workspace';
export const Route = createFileRoute('/')({
 component: Overview,
});
