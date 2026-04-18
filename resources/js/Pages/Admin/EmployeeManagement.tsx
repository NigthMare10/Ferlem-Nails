import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

import AdminSettingsMenu from '@/Components/admin/AdminSettingsMenu';
import { buildAdminPrimaryNav } from '@/lib/adminNavigation';

type Employee = {
    id: string;
    name: string;
    role: string;
    status: string;
    statusVariant: 'active' | 'paused';
    image: string;
    email: string;
    startDate: string;
    isProtected?: boolean;
};

type Props = {
    title: string;
    employees: Employee[];
    specializationOptions: string[];
    summaryCards: Array<{ value: string; label: string }>;
};

export default function EmployeeManagement({ title, employees, specializationOptions, summaryCards }: Props) {
    const [editingId, setEditingId] = useState<string | null>(null);
    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };
    const navItems = useMemo(() => buildAdminPrimaryNav('employees'), []);

    const { data, setData, reset, processing, errors, clearErrors, transform, post, put } = useForm({
        name: '',
        role: specializationOptions[0] ?? '',
        email: '',
        startDate: '',
        password: '',
    });

    const editingEmployee = useMemo(() => employees.find((employee) => employee.id === editingId) ?? null, [employees, editingId]);
    const isEditing = Boolean(editingEmployee);

    useEffect(() => {
        if (!editingEmployee) {
            return;
        }

        setData({
            name: editingEmployee.name,
            role: editingEmployee.role,
            email: editingEmployee.email,
            startDate: editingEmployee.startDate,
            password: '',
        });
    }, [editingEmployee, setData]);

    const openNewEmployee = () => {
        setEditingId(null);
        clearErrors();
        reset();
        setData('role', specializationOptions[0] ?? '');
        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    };

    const discardDraft = () => {
        setEditingId(null);
        clearErrors();
        reset();
        setData('role', specializationOptions[0] ?? '');
    };

    const editEmployee = (employee: Employee) => {
        setEditingId(employee.id);
        clearErrors();
        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    };

    const submitEmployee = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        transform((payload) => ({
            ...payload,
            email: payload.email.trim().toLowerCase(),
        }));

        const handlers = {
            preserveScroll: true,
            onSuccess: () => {
                discardDraft();
            },
        };

        if (editingEmployee) {
            put(route('empleados.admin.update', editingEmployee.id), handlers);
            return;
        }

        post(route('empleados.admin.store'), handlers);
    };

    const deleteEmployee = (employee: Employee) => {
        router.delete(route('empleados.admin.destroy', employee.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (editingId === employee.id) {
                    discardDraft();
                }
            },
        });
    };

    return (
        <div className="flex min-h-screen bg-surface text-on-surface">
            <Head title={title} />

            <nav className="fixed left-0 top-0 flex h-full w-64 flex-col bg-[#f6f3f2] px-6 py-8 transition-all duration-300 ease-in-out">
                <div className="mb-12">
                    <h1 className="font-serif text-xl text-stone-800">FERLEM NAILS</h1>
                    <p className="mt-1 text-[10px] uppercase tracking-widest text-stone-500">Operación Profesional</p>
                </div>

                <div className="flex-1 space-y-2">
                    {navItems.map((item) => (
                        <Link
                            key={item.label}
                            href={item.href}
                            className={`flex items-center gap-4 px-4 py-3 transition-all ${item.active ? 'border-r-2 border-[#7d562d] font-bold text-[#7d562d]' : 'text-stone-500 hover:text-stone-900'}`}
                        >
                            <span className="material-symbols-outlined">{item.icon}</span>
                            <span className="text-xs font-label uppercase tracking-widest">{item.label}</span>
                        </Link>
                    ))}
                </div>

                <div className="mt-auto space-y-4 pt-8">
                    <button type="button" onClick={openNewEmployee} className="flex w-full items-center justify-center gap-2 rounded-md bg-gradient-to-br from-primary to-primary-container py-3 text-xs font-bold uppercase tracking-wider text-on-primary">
                        <span className="material-symbols-outlined text-sm">add</span>
                        Nuevo Empleado
                    </button>
                    <div className="space-y-1 border-t border-outline-variant/20 pt-6">
                        <AdminSettingsMenu showSettingsLink />
                    </div>
                </div>
            </nav>

            <main className="ml-64 min-h-screen flex-1">
                <header className="flex w-full items-center justify-between bg-[#fcf9f8] px-8 py-6">
                    <div className="flex flex-col">
                        <h2 className="text-2xl font-serif italic text-stone-900">Gestión de Empleados</h2>
                        <span className="mt-1 text-xs font-label uppercase tracking-widest text-stone-500">Administrador de FERLEM NAILS</span>
                    </div>

                    <div className="flex items-center gap-6">
                        <div className="flex items-center gap-4 text-stone-500">
                            <button type="button" className="material-symbols-outlined rounded-full p-2 transition-colors hover:bg-[#f6f3f2]">notifications</button>
                            <AdminSettingsMenu iconOnly showSettingsLink />
                        </div>
                        <div className="h-10 w-10 overflow-hidden rounded-full border border-primary/20">
                            <img alt="Perfil administrativo" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDcQrO6sORR9dpwsKknnkBP1vrQI9TBIUopEb37jPeN_D6qTIH-7sKFLexMULsPJBygzcBr9UdOr1tFARC0XZwVg4jYF5oGZ1qD2d-kpAG3CSfNMn5Ha2s5BDeLYF1v9oOVtP5I_OhW3amIOddmyRaTjrMWeD5fFGPtl58HIPt4xIXUx3kX1p5mJq6av0sUHuhQz0Jx8qTr8fzgwR0IIBtRYzeey-rzJ4KIGj4t4OKS6_K1AfEka_-pwIboXV8xPDXrS80vL8pDVcY1" />
                        </div>
                    </div>
                </header>

                <div className="space-y-12 px-8 py-10">
                    {flash?.success ? <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-700">{flash.success}</div> : null}
                    {flash?.error ? <div className="rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">{flash.error}</div> : null}

                    <section className="flex items-end justify-between">
                        <div className="max-w-xl">
                            <h3 className="text-4xl font-serif leading-tight text-on-surface">Nuestro Colectivo Curado</h3>
                            <p className="mt-4 font-body leading-relaxed text-on-surface-variant">
                                Gestiona al equipo que define la experiencia de FERLEM NAILS. Desde especialistas senior hasta consultores estéticos, el talento es el corazón del salón.
                            </p>
                        </div>
                        <button type="button" onClick={openNewEmployee} className="flex items-center gap-3 rounded-md bg-gradient-to-r from-primary to-primary-container px-8 py-4 text-on-primary shadow-lg transition-transform hover:scale-105 active:scale-95">
                            <span className="material-symbols-outlined">person_add</span>
                            <span className="font-label text-sm font-bold uppercase tracking-widest">Nuevo Empleado</span>
                        </button>
                    </section>

                    <section className="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                        {employees.map((employee) => (
                            <div key={employee.id} className="group relative rounded-xl bg-surface-container-low p-6 transition-all duration-300 hover:bg-surface-container-lowest">
                                <div className="mb-6 aspect-[4/5] overflow-hidden rounded-xl grayscale transition-all duration-500 group-hover:grayscale-0">
                                    <img alt={employee.name} className="h-full w-full object-cover" src={employee.image} />
                                </div>
                                <div className="flex items-start justify-between">
                                    <div>
                                        <h4 className="text-xl font-serif text-on-surface">{employee.name}</h4>
                                        <p className="mt-1 text-xs font-label uppercase tracking-widest text-primary">{employee.role}</p>
                                    </div>
                                    <span className={`rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-tighter ${employee.statusVariant === 'active' ? 'bg-secondary-container text-on-secondary-container' : 'bg-surface-dim text-on-surface-variant'}`}>
                                        {employee.status}
                                    </span>
                                </div>
                                <div className="mt-3 text-sm text-stone-500">
                                    <p>{employee.email}</p>
                                    <p>Ingreso: {employee.startDate}</p>
                                </div>
                                <div className="mt-6 flex items-center justify-between opacity-0 transition-opacity group-hover:opacity-100">
                                    <button type="button" onClick={() => editEmployee(employee)} className="flex items-center gap-1 text-xs font-label uppercase tracking-widest text-stone-500 transition-colors hover:text-stone-900">
                                        <span className="material-symbols-outlined text-sm">edit</span>
                                        Editar
                                    </button>
                                    {employee.isProtected ? (
                                        <span className="text-[10px] font-label uppercase tracking-widest text-stone-400">Perfil protegido</span>
                                    ) : (
                                        <button type="button" onClick={() => deleteEmployee(employee)} className="flex items-center gap-1 text-xs font-label uppercase tracking-widest text-error hover:underline">
                                            <span className="material-symbols-outlined text-sm">delete</span>
                                            Eliminar
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </section>

                    <section className="mx-auto max-w-4xl rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-12 shadow-sm">
                        <div className="mb-10 text-center">
                            <h3 className="text-2xl font-serif italic text-on-surface">{isEditing ? 'Editar Perfil Operativo' : 'Registrar Nuevo Talento'}</h3>
                            <div className="mx-auto mt-4 h-[2px] w-12 bg-primary-container"></div>
                        </div>

                        <form className="space-y-8" onSubmit={submitEmployee}>
                            <div className="grid grid-cols-1 gap-8 md:grid-cols-2">
                                <div className="space-y-1">
                                    <label className="px-1 text-[10px] font-label uppercase tracking-widest text-stone-500">Nombre Profesional Completo</label>
                                    <input className="w-full border-0 border-b-2 border-surface-container-highest bg-surface-container-low px-1 py-3 font-body text-on-surface transition-all placeholder:text-stone-300 focus:border-primary focus:ring-0" placeholder="Ej. Elena Rossi" type="text" value={data.name} onChange={(event) => setData('name', event.target.value)} />
                                    {errors.name ? <p className="text-xs text-error">{errors.name}</p> : null}
                                </div>
                                <div className="space-y-1">
                                    <label className="px-1 text-[10px] font-label uppercase tracking-widest text-stone-500">Especialización</label>
                                    <select className="w-full border-0 border-b-2 border-surface-container-highest bg-surface-container-low px-1 py-3 font-body text-on-surface focus:border-primary focus:ring-0" value={data.role} onChange={(event) => setData('role', event.target.value)}>
                                        {specializationOptions.map((option) => <option key={option} value={option}>{option}</option>)}
                                    </select>
                                    {errors.role ? <p className="text-xs text-error">{errors.role}</p> : null}
                                </div>
                                <div className="space-y-1">
                                    <label className="px-1 text-[10px] font-label uppercase tracking-widest text-stone-500">Correo Laboral</label>
                                    <input className="w-full border-0 border-b-2 border-surface-container-highest bg-surface-container-low px-1 py-3 font-body text-on-surface placeholder:text-stone-300 focus:border-primary focus:ring-0" placeholder="elena@ferlemnails.local" type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} />
                                    {errors.email ? <p className="text-xs text-error">{errors.email}</p> : null}
                                </div>
                                <div className="space-y-1">
                                    <label className="px-1 text-[10px] font-label uppercase tracking-widest text-stone-500">Fecha de Inicio</label>
                                    <input className="w-full border-0 border-b-2 border-surface-container-highest bg-surface-container-low px-1 py-3 font-body text-on-surface focus:border-primary focus:ring-0" type="date" value={data.startDate} onChange={(event) => setData('startDate', event.target.value)} />
                                    {errors.startDate ? <p className="text-xs text-error">{errors.startDate}</p> : null}
                                </div>
                                <div className="space-y-1 md:col-span-2">
                                    <label className="px-1 text-[10px] font-label uppercase tracking-widest text-stone-500">Contraseña {isEditing ? '(solo si deseas cambiarla)' : ''}</label>
                                    <input className="w-full border-0 border-b-2 border-surface-container-highest bg-surface-container-low px-1 py-3 font-body text-on-surface placeholder:text-stone-300 focus:border-primary focus:ring-0" placeholder={isEditing ? 'Nueva contraseña opcional' : 'Define la contraseña del perfil'} type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} />
                                    {errors.password ? <p className="text-xs text-error">{errors.password}</p> : null}
                                </div>
                            </div>

                            <div className="flex items-center justify-end gap-6 pt-6">
                                <button type="button" onClick={discardDraft} className="text-xs font-label uppercase tracking-widest text-stone-500 transition-colors hover:text-stone-900">Descartar</button>
                                <button type="submit" disabled={processing} className="rounded-md bg-primary px-10 py-4 text-xs font-bold uppercase tracking-widest text-on-primary shadow-md transition-all hover:bg-primary-container hover:text-on-primary-container disabled:cursor-not-allowed disabled:opacity-70">
                                    {processing ? 'Guardando...' : isEditing ? 'Guardar Cambios' : 'Completar Registro'}
                                </button>
                            </div>
                        </form>
                    </section>

                    <section className="grid grid-cols-1 gap-6 pt-12 md:grid-cols-3">
                        {summaryCards.map((card) => (
                            <div key={card.label} className="flex flex-col items-center rounded-xl bg-[#f6f3f2] p-8 text-center">
                                <span className="text-4xl font-serif text-primary">{card.value}</span>
                                <p className="mt-2 text-[10px] font-label uppercase tracking-widest text-stone-500">{card.label}</p>
                            </div>
                        ))}
                    </section>
                </div>

                <footer className="mt-20 border-t border-outline-variant/10 px-8 py-12 text-center">
                    <p className="text-xs font-label uppercase tracking-[0.2em] text-stone-400">Portal Administrativo de FERLEM NAILS • Est. 2026</p>
                </footer>
            </main>
        </div>
    );
}
