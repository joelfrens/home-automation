import { Form, Head, Link, usePage } from '@inertiajs/react';
import FamilyController from '@/actions/App/Http/Controllers/Settings/FamilyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/family';

type Member = {
    id: number;
    name: string;
    email: string;
    isAdmin: boolean;
    isYou: boolean;
};

export default function Family({ members }: { members: Member[] }) {
    // Errors from remove / admin toggle (e.g. "The household needs at least one admin.").
    const { errors } = usePage<{ errors: Record<string, string> }>().props;

    return (
        <>
            <Head title="Family" />

            <h1 className="sr-only">Family</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Family members"
                    description="Everyone who can log in. Admins can add and remove members."
                />

                <InputError message={errors.member} />

                <ul className="divide-y rounded-lg border">
                    {members.map((member) => (
                        <li
                            key={member.id}
                            className="flex flex-wrap items-center gap-3 p-4"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="flex items-center gap-2 font-medium">
                                    {member.name}
                                    {member.isYou && (
                                        <span className="text-sm text-muted-foreground">
                                            (you)
                                        </span>
                                    )}
                                    {member.isAdmin && <Badge>Admin</Badge>}
                                </p>
                                <p className="truncate text-sm text-muted-foreground">
                                    {member.email}
                                </p>
                            </div>

                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={FamilyController.toggleAdmin(
                                        member.id,
                                    )}
                                    as="button"
                                    preserveScroll
                                >
                                    {member.isAdmin
                                        ? 'Remove admin'
                                        : 'Make admin'}
                                </Link>
                            </Button>

                            {!member.isYou && <RemoveMember member={member} />}
                        </li>
                    ))}
                </ul>
            </div>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Add a family member"
                    description="They can log in straight away and change their password in Settings → Security."
                />

                <Form
                    {...FamilyController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoComplete="off"
                                    placeholder="Name"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    autoComplete="off"
                                    placeholder="Email address"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    Temporary password
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="new-password"
                                    placeholder="Temporary password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    placeholder="Confirm password"
                                />
                            </div>

                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="is_admin"
                                    name="is_admin"
                                    value="1"
                                />
                                <Label htmlFor="is_admin">
                                    Admin (can manage family members)
                                </Label>
                            </div>

                            <Button disabled={processing}>Add member</Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

function RemoveMember({ member }: { member: Member }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    Remove
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Remove {member.name}?</DialogTitle>
                <DialogDescription>
                    {member.name} will no longer be able to log in. You can add
                    them again later.
                </DialogDescription>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <DialogClose asChild>
                        <Button variant="destructive" asChild>
                            <Link
                                href={FamilyController.destroy(member.id)}
                                as="button"
                                preserveScroll
                            >
                                Remove
                            </Link>
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

Family.layout = {
    breadcrumbs: [
        {
            title: 'Family',
            href: index(),
        },
    ],
};
