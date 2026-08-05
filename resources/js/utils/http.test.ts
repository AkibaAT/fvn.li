import { AxiosError, type AxiosRequestConfig } from 'axios';
import { beforeEach, describe, expect, test } from 'vitest';
import http from './http';

function failingAdapter(status: number, data: unknown) {
    return async (config: AxiosRequestConfig) => {
        const response = { data, status, statusText: '', headers: {}, config: config as never };
        throw new AxiosError(`Request failed with status code ${status}`, AxiosError.ERR_BAD_REQUEST, config as never, null, response as never);
    };
}

describe('http error normalization', () => {
    beforeEach(() => {
        document.head.innerHTML = '<meta name="csrf-token" content="token">';
    });

    test('surfaces the server message on non-2xx responses', async () => {
        http.defaults.adapter = failingAdapter(422, { success: false, message: 'You have already reported this review.' });

        await expect(http.post('/browser-api/review-reports/1')).rejects.toThrow('You have already reported this review.');
    });

    test('keeps the response payload available to callers', async () => {
        http.defaults.adapter = failingAdapter(401, { message: 'You must be logged in.' });

        const error = await http.get('/browser-api/bug-reports/1').catch((e: AxiosError<{ message: string }>) => e);
        expect(error).toBeInstanceOf(AxiosError);
        expect((error as AxiosError<{ message: string }>).response?.data.message).toBe('You must be logged in.');
    });

    test('falls back to the axios message when the body has none', async () => {
        http.defaults.adapter = failingAdapter(500, '<html>error</html>');

        await expect(http.get('/browser-api/bug-reports')).rejects.toThrow('Request failed with status code 500');
    });
});
