export interface MerchVariant {
    name: string;
    image_path?: string | null;
    image_url: string | null;
}

export interface MerchItem {
    id: number;
    name: string;
    description: string | null;
    price: number;
    currency: string;
    formatted_price: string;
    image_url: string | null;
    variants?: MerchVariant[];
    stock: number;
    is_out_of_stock: boolean;
    stock_label: string;
    url: string;
}
