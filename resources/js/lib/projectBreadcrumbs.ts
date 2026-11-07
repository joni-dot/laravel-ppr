import ProjectController from '@/actions/App/Http/Controllers/Projects/ProjectController';
import { trans } from '@/lib/i18n';
import type { BreadcrumbItem, ProjectHeader } from '@/types';

/**
 * Projects › <project name> › <section>, for a project page's layout options.
 */
export function projectBreadcrumbs(
    project: ProjectHeader,
    section?: BreadcrumbItem,
): { breadcrumbs: BreadcrumbItem[] } {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: trans('Projects'), href: ProjectController.index() },
        { title: project.name, href: ProjectController.show(project.id) },
    ];

    return { breadcrumbs: section ? [...breadcrumbs, section] : breadcrumbs };
}
