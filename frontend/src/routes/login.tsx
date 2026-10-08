import {createFileRoute} from '@tanstack/react-router';
import {LoginPage} from '@/components/validity/links';

export const Route = createFileRoute('/login')({
  head: () => ({
    meta: [
      { title: 'Sign in — Validity' },
      { name: 'description', content: 'Sign in to the Validity usher workspace.' },
    ],
  }),
  component: LoginPage,
});
