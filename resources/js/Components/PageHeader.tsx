import { ReactNode } from 'react';

type Props = {
    eyebrow?: string;
    title: string;
    description?: string;
    actions?: ReactNode;
};

export default function PageHeader({ eyebrow, title, description, actions }: Props) {
    return (
        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div className="max-w-2xl">
                {eyebrow ? <p className="mb-2 text-xs uppercase tracking-[0.35em] text-brand-rose">{eyebrow}</p> : null}
                <h1 className="font-display text-4xl text-stone-900 md:text-5xl">{title}</h1>
                {description ? <p className="mt-3 text-sm leading-7 text-stone-600 md:text-base">{description}</p> : null}
            </div>
            {actions ? <div className="flex flex-wrap items-center gap-3">{actions}</div> : null}
        </div>
    );
}
