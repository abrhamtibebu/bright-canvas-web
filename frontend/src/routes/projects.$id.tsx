import { createFileRoute } from '@tanstack/react-router';
import { ProjectDetail } from '@/components/validity/workspace';
export const Route = createFileRoute('/projects/$id')({
 head: () => ({ meta: [{title:'Project Staffing — Validity'}, {name:'description',content:'Manage project shortlists, invitations, attendance and ratings.'},{property:'og:title',content:'Project Staffing — Validity'},{property:'og:description',content:'Manage project shortlists, invitations, attendance and ratings.'},{property:'og:type',content:'website'},{name:'twitter:card',content:'summary_large_image'}] }),
 component: ProjectPage,
});
function ProjectPage(){const {id}=Route.useParams();return <ProjectDetail id={id}/>}
