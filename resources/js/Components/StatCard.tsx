import { ReactNode } from 'react';

type Props = {
    label: string;
    value: string;
    helper?: string;
    icon?: ReactNode;
};

export default function StatCard({ label, value, helper, icon }: Props) {
    return (
        <div className="rounded-3xl border border-white/70 bg-white/85 p-6 shadow-[0_20px_60px_rgba(107,72,63,0.08)] backdrop-blur">
            <div className="mb-4 flex items-center justify-between">
                <span className="text-xs uppercase tracking-[0.3em] text-stone-500">{label}</span>
                <span className="text-brand-copper">{icon}</span>
            </div>
            <div className="font-display text-4xl text-stone-900">{value}</div>
            {helper ? <p className="mt-2 text-sm text-stone-500">{helper}</p> : null}
        </div>
    );
}
