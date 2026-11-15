export type DeviceStatus = 'pending' | 'active' | 'revoked';

export type DeviceRow = {
    id: string;
    name: string;
    site: string | null;
    location: string | null;
    status: DeviceStatus;
    online: boolean;
    last_seen_at: string | null;
    firmware_version: string | null;
    hardware_id: string | null;
};

export type SiteOption = {
    id: number;
    name: string;
    locations: { id: number; site_id: number; name: string }[];
};

export type PairingFlash = {
    device_id: string;
    code: string;
    expires_at: string;
};

export type DeviceStatusPayload = {
    device_id: string;
    status: DeviceStatus;
    online: boolean;
    last_seen_at: string | null;
    firmware_version: string | null;
};

export type DevicePairedPayload = {
    device_id: string;
    name: string;
    hardware_id: string | null;
};

export type TemplateCounts = {
    face_mfn_s8_v1: number;
    fp_zn600: number;
};

export type EmployeeRow = {
    id: string;
    name: string;
    code: string | null;
    status: 'active' | 'inactive';
    all_devices: boolean;
    device_ids: string[];
    devices_count: number;
    templates: TemplateCounts;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
