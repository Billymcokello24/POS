/* eslint-disable import/order */
import { computed, ref } from 'vue';
import axios from '@/axios';
import { postJsonWithSanctum } from '@/lib/sanctum';

import { qrCode, recoveryCodes, secretKey } from '@/routes/two-factor/index';

const fetchJson = async <T>(url: string): Promise<T> => {
    const response = await axios.get(url, { headers: { Accept: 'application/json' } });

    if (response.status < 200 || response.status >= 300) {
        throw new Error(`Failed to fetch: ${response.status}`);
    }

    return response.data as T;
};

const errors = ref<string[]>([]);
const manualSetupKey = ref<string | null>(null);
const qrCodeSvg = ref<string | null>(null);
const recoveryCodesList = ref<string[]>([]);

const hasSetupData = computed<boolean>(
    () => qrCodeSvg.value !== null && manualSetupKey.value !== null,
);

export const useTwoFactorAuth = () => {
    const fetchQrCode = async (): Promise<void> => {
        try {
            const { svg } = await fetchJson<{ svg: string; url: string }>(
                qrCode.url(),
            );

            qrCodeSvg.value = svg;
        } catch {
            errors.value.push('Failed to fetch QR code');
            qrCodeSvg.value = null;
        }
    };

    const fetchSetupKey = async (): Promise<void> => {
        try {
            const { secretKey: key } = await fetchJson<{ secretKey: string }>(
                secretKey.url(),
            );

            manualSetupKey.value = key;
        } catch {
            errors.value.push('Failed to fetch a setup key');
            manualSetupKey.value = null;
        }
    };

    const clearSetupData = (): void => {
        manualSetupKey.value = null;
        qrCodeSvg.value = null;
        clearErrors();
    };

    const clearErrors = (): void => {
        errors.value = [];
    };

    const clearTwoFactorAuthData = (): void => {
        clearSetupData();
        clearErrors();
        recoveryCodesList.value = [];
    };

    const fetchRecoveryCodes = async (): Promise<void> => {
        try {
            clearErrors();
            recoveryCodesList.value = await fetchJson<string[]>(
                recoveryCodes.url(),
            );
        } catch {
            errors.value.push('Failed to fetch recovery codes');
            recoveryCodesList.value = [];
        }
    };

    const fetchSetupData = async (): Promise<void> => {
        try {
            clearErrors();
            await Promise.all([fetchQrCode(), fetchSetupKey()]);
        } catch {
            qrCodeSvg.value = null;
            manualSetupKey.value = null;
        }
    };

    return {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        errors,
        hasSetupData,
        clearSetupData,
        clearErrors,
        clearTwoFactorAuthData,
        fetchQrCode,
        fetchSetupKey,
        fetchSetupData,
        fetchRecoveryCodes,
    };
};

export async function postTwoFactor(url: string, payload: any) {
    // centralize POST through Sanctum helper so XSRF token and credentials are handled consistently
    return await postJsonWithSanctum(url, payload);
}
