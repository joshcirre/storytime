import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 2c1.2 9.6 4.5 13.4 15 15-10.5 1.6-13.8 5.4-15 15-1.2-9.6-4.5-13.4-15-15 10.5-1.6 13.8-5.4 15-15Z" />
            <path d="M33 26c.55 4.4 2.1 6.2 7 7-4.9.8-6.45 2.6-7 7-.55-4.4-2.1-6.2-7-7 4.9-.8 6.45-2.6 7-7Z" />
        </svg>
    );
}
