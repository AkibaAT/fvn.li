import {ref, Ref} from 'vue';

export interface LoadingState<T> {
    current: Ref<T | null>;
    pending: Ref<T | null>;
    isLoading: Ref<boolean>;
    error: Ref<string | null>;
    startLoading: (data: T) => void;
    finishLoading: (error?: string) => void;
}

export function useLoadingState<T>(): LoadingState<T> {
    const current = ref<T | null>(null) as Ref<T | null>;
    const pending = ref<T | null>(null) as Ref<T | null>;
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    const startLoading = (data: T) => {
        pending.value = data;
        isLoading.value = true;
        error.value = null;
    };

    const finishLoading = (errorMsg?: string) => {
        if (errorMsg) {
            error.value = errorMsg;
        } else {
            current.value = pending.value;
            error.value = null;
        }
        isLoading.value = false;
        pending.value = null;
    };

    return {
        current,
        pending,
        isLoading,
        error,
        startLoading,
        finishLoading
    };
}
