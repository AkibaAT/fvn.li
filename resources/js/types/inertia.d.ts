import '@inertiajs/core';
import type {SharedData} from './index';

declare module '@inertiajs/core' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type
    interface PageProps extends SharedData {
    }
}
