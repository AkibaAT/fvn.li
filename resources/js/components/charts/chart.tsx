import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    ChartData,
    ChartOptions,
    Legend,
    LinearScale,
    LineElement,
    type Plugin,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import React from 'react';
import {Bar, Line} from 'react-chartjs-2';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
);

interface LineChartProps extends React.HTMLAttributes<HTMLDivElement> {
    type?: 'line';
    data: ChartData<'line'>;
    options?: ChartOptions<'line'>;
    style?: React.CSSProperties;
    className?: string;
    plugins?: Plugin<'line'>[];
}

interface BarChartProps extends React.HTMLAttributes<HTMLDivElement> {
    type: 'bar';
    data: ChartData<'bar'>;
    options?: ChartOptions<'bar'>;
    style?: React.CSSProperties;
    className?: string;
    plugins?: Plugin<'bar'>[];
}

type ChartProps = LineChartProps | BarChartProps;

const Chart: React.FC<ChartProps> = ({
                                         type = 'line',
                                         data,
                                         options,
                                         style,
                                         className,
                                         plugins,
                                     }) => {
    return (
        <div style={style} className={className}>
            {type === 'bar' ? (
                <Bar
                    data={data as ChartData<'bar'>}
                    options={options as ChartOptions<'bar'>}
                    plugins={plugins as Plugin<'bar'>[]}
                />
            ) : (
                <Line
                    data={data as ChartData<'line'>}
                    options={options as ChartOptions<'line'>}
                    plugins={plugins as Plugin<'line'>[]}
                />
            )}
        </div>
    );
};

export default Chart;
