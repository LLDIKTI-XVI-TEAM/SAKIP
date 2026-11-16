import { Head, usePage } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import type { SharedPageProps } from '@/types/auth';
import { readRoleAssignmentOutcome, RoleAssignmentPjNotice, roleAssignmentMessages, roleAssignmentUnknown } from './RoleAssignmentFeedback';

export default function RoleAssignmentResult() {
    const page = usePage<SharedPageProps>();
    const { props: { auth } } = page;
    const outcome = readRoleAssignmentOutcome(page, 'Access/RoleAssignmentResult');
    const message = outcome ? roleAssignmentMessages[outcome.status] : roleAssignmentUnknown;
    return <AuthenticatedLayout title="Hasil penetapan peran" hasCustomHeading>
        <Head title="Hasil penetapan peran" />
        <section className="max-w-2xl rounded-xl border border-border bg-surface p-6">
            <h1 className="text-lg font-semibold" aria-live="polite">{message}</h1>
            {!outcome && <p className="mt-3 text-sm text-ink">Minta administrator yang masih memiliki akses untuk memeriksa peran terbaru akun ini.</p>}
            <RoleAssignmentPjNotice outcome={outcome} />
            {outcome && <div className="mt-5 rounded-lg bg-soft p-4 text-sm">
                <p className="font-semibold">Akses pengelolaan peran tidak tersedia untuk akun Anda.</p>
                {auth.user?.role && <p className="mt-2">Peran aktif: <strong>{auth.user.role.toUpperCase()}</strong>.</p>}
            </div>}
        </section>
    </AuthenticatedLayout>;
}
