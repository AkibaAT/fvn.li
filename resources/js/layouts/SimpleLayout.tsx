import React, {ReactNode} from 'react';
import Container from '@/components/container';

interface SimpleLayoutProps {
    children: ReactNode;
    title?: string;
}

export default function SimpleLayout({children, title}: SimpleLayoutProps) {
    return (
        <>
            {title && <title>{title}</title>}
            <Container>
                {children}
            </Container>
        </>
    );
}