import { SVGAttributes } from 'react';

/** Логото на Decorron: стилизирана балонена арка. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M4 21c0-7.18 3.58-13 8-13s8 5.82 8 13" fill="none" strokeLinecap="round" />
            <circle cx="12" cy="8" r="2.4" fill="currentColor" stroke="none" />
            <circle cx="6.6" cy="15.5" r="1.9" fill="currentColor" stroke="none" />
            <circle cx="17.4" cy="15.5" r="1.9" fill="currentColor" stroke="none" />
        </svg>
    );
}
