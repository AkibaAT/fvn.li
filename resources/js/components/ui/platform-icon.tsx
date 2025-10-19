import React from 'react';
import SteamIcon from '@/components/icons/steam';
import ItchioIcon from '@/components/icons/itchio';

interface PlatformIconProps {
    platform: string;
    className?: string;
    showTooltip?: boolean;
}

const PlatformIcon: React.FC<PlatformIconProps> = ({
    platform,
    className = '',
    showTooltip = true,
}) => {
    const getPlatformInfo = () => {
        switch (platform) {
            case 'steam':
                return {
                    name: 'Steam',
                    icon: <SteamIcon className={`h-4 w-4 ${className}`} />,
                };
            case 'itch_io':
            default:
                return {
                    name: 'itch.io',
                    icon: <ItchioIcon className={`h-4 w-4 ${className}`} />,
                };
        }
    };

    const { name, icon } = getPlatformInfo();

    return (
        <span
            className={`inline-flex items-center ${className}`}
            title={showTooltip ? `From ${name}` : undefined}
        >
            {icon}
        </span>
    );
};

export default PlatformIcon;

